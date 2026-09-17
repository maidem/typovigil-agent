<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Service;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\PackageManager;

/**
 * Collects what is installed on this instance.
 */
final readonly class PackageCollector
{
    public function __construct(
        private PackageManager $packageManager,
        private Typo3Version $typo3Version,
    ) {}

    /**
     * @return array{coreVersion: string, packages: list<array<string, mixed>>}
     */
    public function collect(): array
    {
        $coreVersion = $this->typo3Version->getVersion();
        $installed = $this->installedVersions();

        $packages = [[
            'composerName' => 'typo3/cms-core',
            'extensionKey' => 'core',
            'version' => $coreVersion,
            'isCore' => true,
        ]];

        foreach ($this->packageManager->getActivePackages() as $package) {
            $key = $package->getPackageKey();
            if ($key === 'core') {
                continue;
            }

            $composerName = (string)($package->getValueFromComposerManifest('name') ?? '');

            $packages[] = [
                'composerName' => $composerName,
                'extensionKey' => $key,
                // installed.php carries the version Composer actually resolved;
                // ext_emconf often lags behind or says something else entirely.
                'version' => $installed[$composerName] ?? $this->versionFromPackage($package),
                'isCore' => false,
            ];
        }

        return [
            'coreVersion' => $coreVersion,
            'packages' => $packages,
        ];
    }

    /**
     * Versions as resolved by Composer, keyed by package name.
     *
     * @return array<string, string>
     */
    private function installedVersions(): array
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return [];
        }

        $versions = [];
        foreach (\Composer\InstalledVersions::getInstalledPackages() as $name) {
            $version = \Composer\InstalledVersions::getPrettyVersion($name);
            if ($version !== null) {
                $versions[$name] = $version;
            }
        }

        return $versions;
    }

    private function versionFromPackage(object $package): string
    {
        if (method_exists($package, 'getPackageMetaData')) {
            $version = $package->getPackageMetaData()->getVersion();
            if (is_string($version) && $version !== '') {
                return $version;
            }
        }

        return '';
    }
}
