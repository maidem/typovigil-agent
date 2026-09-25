<?php

declare(strict_types=1);

namespace Maidemde\TypovigilAgent\Command;

use Maidemde\TypovigilAgent\Service\ReportSender;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports on demand — meant for a deploy's entrypoint, right after
 * `extension:setup`, so a composer version bump reaches the hub the moment
 * it goes live instead of waiting for the next daily run. The version-bump
 * case has no event of its own: PackageChangeListener only fires on
 * install/uninstall, not on an already-active package moving versions.
 */
final class SendReportCommand extends Command
{
    public function __construct(private readonly ReportSender $reportSender)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sent = $this->reportSender->send();
        $output->writeln($sent ? '<info>Report sent.</info>' : '<comment>Report not sent — see the log.</comment>');

        return $sent ? Command::SUCCESS : Command::FAILURE;
    }
}
