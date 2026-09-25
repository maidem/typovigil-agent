<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Tests\Unit\Service;

use Maidemde\TypovigilAgent\Service\PackageCollector;
use Maidemde\TypovigilAgent\Service\ReportSender;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Registry;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ReportSenderTest extends UnitTestCase
{
    /**
     * The report a real PackageCollector produces from the stubbed
     * PackageManager/Typo3Version below (no active packages beyond core).
     *
     * @return array{coreVersion: string, packages: list<array<string, mixed>>}
     */
    private const STUB_REPORT = [
        'coreVersion' => '13.4.0',
        'packages' => [[
            'composerName' => 'typo3/cms-core',
            'extensionKey' => 'core',
            'version' => '13.4.0',
            'isCore' => true,
        ]],
    ];

    protected function tearDown(): void
    {
        putenv('TYPOVIGIL_AGENT_HUB_URL');
        putenv('TYPOVIGIL_AGENT_TOKEN');
        parent::tearDown();
    }

    /**
     * @param string|false $hubUrl false leaves the variable unset
     * @param string|false $token false leaves the variable unset
     */
    private function setEnv(string|false $hubUrl, string|false $token): void
    {
        $hubUrl === false ? putenv('TYPOVIGIL_AGENT_HUB_URL') : putenv('TYPOVIGIL_AGENT_HUB_URL=' . $hubUrl);
        $token === false ? putenv('TYPOVIGIL_AGENT_TOKEN') : putenv('TYPOVIGIL_AGENT_TOKEN=' . $token);
    }

    /**
     * PackageCollector is final, so it cannot be mocked. Its own dependencies
     * are cheap to stub instead, and a real instance built from them always
     * returns self::STUB_REPORT - fine, since these tests only care what
     * ReportSender does with whatever collect() returns.
     *
     * @return array{0: PackageCollector, 1: RequestFactory, 2: Registry, 3: LoggerInterface}
     */
    private function collaborators(): array
    {
        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->method('getActivePackages')->willReturn([]);
        $typo3Version = $this->createMock(Typo3Version::class);
        $typo3Version->method('getVersion')->willReturn('13.4.0');

        return [
            new PackageCollector($packageManager, $typo3Version),
            $this->createMock(RequestFactory::class),
            $this->createMock(Registry::class),
            $this->createMock(LoggerInterface::class),
        ];
    }

    #[DataProvider('secureHubUrlProvider')]
    public function testIsSecureHubUrlEnforcesHttpsExceptForLocalhost(string $hubUrl, bool $expected): void
    {
        self::assertSame($expected, ReportSender::isSecureHubUrl($hubUrl));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function secureHubUrlProvider(): array
    {
        return [
            'https is secure' => ['https://hub.example.com', true],
            'https with trailing slash' => ['https://hub.example.com/', true],
            'localhost is allowed over http' => ['http://localhost', true],
            'localhost with port is allowed' => ['http://localhost:8080', true],
            'plain http is rejected' => ['http://hub.example.com', false],
            'lookalike localhost subdomain is rejected' => ['http://localhost.attacker.com', false],
            'empty string is rejected' => ['', false],
        ];
    }

    public function testSendReturnsFalseWhenNotConfigured(): void
    {
        [$collector, $requestFactory, $registry, $logger] = $this->collaborators();
        $this->setEnv(false, false);

        $requestFactory->expects(self::never())->method('request');
        $logger->expects(self::once())->method('warning');

        $sender = new ReportSender($collector, $requestFactory, $registry, $logger);

        self::assertFalse($sender->send());
    }

    public function testSendRefusesInsecureHubUrl(): void
    {
        [$collector, $requestFactory, $registry, $logger] = $this->collaborators();
        $this->setEnv('http://hub.example.com', 'secret');

        $requestFactory->expects(self::never())->method('request');
        $logger->expects(self::once())->method('error')->with(self::stringContains('must use https'));

        $sender = new ReportSender($collector, $requestFactory, $registry, $logger);

        self::assertFalse($sender->send());
    }

    public function testSendSkipsRequestWhenHashUnchangedAndOnlyOnChange(): void
    {
        [$collector, $requestFactory, $registry, $logger] = $this->collaborators();
        $this->setEnv('https://hub.example.com', 'secret');

        $hash = hash('sha256', json_encode(self::STUB_REPORT, JSON_THROW_ON_ERROR));
        $registry->method('get')->with('typovigil_agent', 'lastReportHash')->willReturn($hash);

        $requestFactory->expects(self::never())->method('request');

        $sender = new ReportSender($collector, $requestFactory, $registry, $logger);

        self::assertTrue($sender->send(onlyOnChange: true));
    }

    public function testSendSendsBearerTokenAsHeaderNotQueryParameter(): void
    {
        [$collector, $requestFactory, $registry, $logger] = $this->collaborators();
        $this->setEnv('https://hub.example.com/', 'secret-token');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $requestFactory->expects(self::once())
            ->method('request')
            ->with(
                'https://hub.example.com/typovigil/report',
                'POST',
                self::callback(function (array $options): bool {
                    self::assertSame('Bearer secret-token', $options['headers']['Authorization']);
                    self::assertStringNotContainsString('secret-token', $options['body']);

                    return true;
                })
            )
            ->willReturn($response);

        $registry->expects(self::once())->method('set')->with('typovigil_agent', 'lastReportHash', self::isString());

        $sender = new ReportSender($collector, $requestFactory, $registry, $logger);

        self::assertTrue($sender->send());
    }

    public function testSendReturnsFalseAndDoesNotUpdateRegistryWhenHubRejects(): void
    {
        [$collector, $requestFactory, $registry, $logger] = $this->collaborators();
        $this->setEnv('https://hub.example.com', 'secret');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $requestFactory->method('request')->willReturn($response);

        $registry->expects(self::never())->method('set');
        $logger->expects(self::once())->method('error')->with(self::stringContains('rejected'), self::anything());

        $sender = new ReportSender($collector, $requestFactory, $registry, $logger);

        self::assertFalse($sender->send());
    }

    public function testSendReturnsFalseWhenRequestThrows(): void
    {
        [$collector, $requestFactory, $registry, $logger] = $this->collaborators();
        $this->setEnv('https://hub.example.com', 'secret');

        $requestFactory->method('request')->willThrowException(new \RuntimeException('connection refused'));

        $registry->expects(self::never())->method('set');
        $logger->expects(self::once())->method('error')->with(self::stringContains('failed'), self::anything());

        $sender = new ReportSender($collector, $requestFactory, $registry, $logger);

        self::assertFalse($sender->send());
    }
}
