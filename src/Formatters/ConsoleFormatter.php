<?php

namespace Phoenix1331\LaravelEnvAudit\Formatters;

use Illuminate\Console\OutputStyle;
use Phoenix1331\LaravelEnvAudit\Data\EnvAuditReport;

class ConsoleFormatter
{
    public function write(EnvAuditReport $report, OutputStyle $output): void
    {
        $score = $report->isolationScore();
        $scoreColor = $score >= 90 ? 'green' : ($score >= 70 ? 'yellow' : 'red');

        $output->newLine();
        $output->writeln(sprintf(
            '  <fg=%s;options=bold>Isolation Score: %d%%</> (%d/%d env() calls live inside config/)',
            $scoreColor,
            $score,
            $report->configEnvCalls,
            $report->totalEnvCalls,
        ));
        $output->newLine();

        $this->writeDirectUsage($report, $output);
        $this->writePossibleSecrets($report, $output);
        $this->writeMissingFromExample($report, $output);
        $this->writeUnusedInExample($report, $output);
        $this->writeEnvDrift($report, $output);
        $this->writeIgnores($report, $output);
        $this->writeExpiredIgnores($report, $output);
        $this->writeMissingReasonIgnores($report, $output);
        $this->writeSkippedFiles($report, $output);
    }

    private function writeDirectUsage(EnvAuditReport $report, OutputStyle $output): void
    {
        $count = count($report->directUsageViolations);

        if ($count === 0) {
            $output->writeln('  <fg=green>✔ Direct usage (0 violations)</>');
            $output->newLine();

            return;
        }

        $output->writeln(sprintf('  <fg=red>✘ Direct usage (%d)</>', $count));

        foreach ($report->directUsageViolations as $call) {
            $rel = $this->relativePath($call->file);
            $key = $call->key ?? '(dynamic)';
            $output->writeln(sprintf(
                '    <fg=gray>%s:%d</> <fg=white>env(\'%s\')</> called outside config/',
                $rel,
                $call->line,
                $key,
            ));
        }

        $output->newLine();
    }

    private function writePossibleSecrets(EnvAuditReport $report, OutputStyle $output): void
    {
        $count = count($report->possibleSecrets);

        if ($count === 0) {
            $output->writeln('  <fg=green>✔ Possible secrets in .env.example (0)</>');
            $output->newLine();

            return;
        }

        $output->writeln(sprintf('  <fg=red>✘ Possible secret in .env.example (%d)</>', $count));

        foreach ($report->possibleSecrets as $secret) {
            $output->writeln(sprintf(
                '    <fg=white>%s</>=<fg=yellow>%s</>  <fg=gray>%s</>',
                $secret->key,
                $secret->maskedValue,
                $secret->detail,
            ));
        }

        $output->newLine();
    }

    private function writeMissingFromExample(EnvAuditReport $report, OutputStyle $output): void
    {
        $count = count($report->missingFromExample);

        if ($count === 0) {
            $output->writeln('  <fg=green>✔ Missing from .env.example (0)</>');
            $output->newLine();

            return;
        }

        $output->writeln(sprintf('  <fg=yellow>⚠ Missing from .env.example (%d)</>', $count));

        foreach ($report->missingFromExample as $key) {
            $output->writeln(sprintf('    <fg=white>%s</>  <fg=gray>used in config, no .env.example entry</>', $key));
        }

        $output->newLine();
    }

    private function writeUnusedInExample(EnvAuditReport $report, OutputStyle $output): void
    {
        $count = count($report->unusedInExample);

        if ($count === 0) {
            $output->writeln('  <fg=green>✔ Unused in .env.example (0)</>');
            $output->newLine();

            return;
        }

        $output->writeln(sprintf('  <fg=blue>ℹ Unused in .env.example (%d)</>', $count));

        foreach ($report->unusedInExample as $key) {
            $output->writeln(sprintf('    <fg=white>%s</>  <fg=gray>in .env.example, never referenced</>', $key));
        }

        $output->newLine();
    }

    private function writeIgnores(EnvAuditReport $report, OutputStyle $output): void
    {
        if (count($report->ignores) === 0) {
            return;
        }

        $output->writeln(sprintf('  <fg=gray>Bypasses (%d active)</>', count($report->ignores)));

        foreach ($report->ignores as $ignore) {
            $rel = $this->relativePath($ignore->file);
            $expiry = $ignore->expires ? " (expires {$ignore->expires})" : '';
            $output->writeln(sprintf(
                '    <fg=gray>%s:%d</>  %s%s  <fg=gray>[%s]</>',
                $rel,
                $ignore->line,
                $ignore->reason,
                $expiry,
                $ignore->source,
            ));
        }

        $output->newLine();
    }

    private function writeExpiredIgnores(EnvAuditReport $report, OutputStyle $output): void
    {
        if (count($report->expiredIgnores) === 0) {
            return;
        }

        $output->writeln(sprintf('  <fg=red>✘ Expired bypasses (%d): these must be resolved or renewed</>', count($report->expiredIgnores)));

        foreach ($report->expiredIgnores as $ignore) {
            $rel = $this->relativePath($ignore->file);
            $output->writeln(sprintf(
                '    <fg=gray>%s:%d</>  %s  <fg=red>expired %s</>',
                $rel,
                $ignore->line,
                $ignore->reason,
                $ignore->expires,
            ));
        }

        $output->newLine();
    }

    private function writeEnvDrift(EnvAuditReport $report, OutputStyle $output): void
    {
        if (count($report->envOnlyKeys) === 0 && count($report->exampleOnlyKeys) === 0) {
            return;
        }

        if (count($report->envOnlyKeys) > 0) {
            $output->writeln(sprintf('  <fg=yellow>⚠ In .env but missing from .env.example (%d)</>', count($report->envOnlyKeys)));

            foreach ($report->envOnlyKeys as $key) {
                $output->writeln(sprintf('    <fg=white>%s</>', $key));
            }

            $output->newLine();
        }

        if (count($report->exampleOnlyKeys) > 0) {
            $output->writeln(sprintf('  <fg=blue>ℹ In .env.example but missing from .env (%d)</>', count($report->exampleOnlyKeys)));

            foreach ($report->exampleOnlyKeys as $key) {
                $output->writeln(sprintf('    <fg=white>%s</>', $key));
            }

            $output->newLine();
        }
    }

    private function writeMissingReasonIgnores(EnvAuditReport $report, OutputStyle $output): void
    {
        if (count($report->missingReasonIgnores) === 0) {
            return;
        }

        $output->writeln(sprintf('  <fg=red>✘ Bypasses missing a reason (%d): add a reason string to each</>', count($report->missingReasonIgnores)));

        foreach ($report->missingReasonIgnores as $ignore) {
            $rel = $this->relativePath($ignore->file);
            $output->writeln(sprintf(
                '    <fg=gray>%s:%d</>  <fg=gray>[%s]</>',
                $rel,
                $ignore->line,
                $ignore->source,
            ));
        }

        $output->newLine();
    }

    private function writeSkippedFiles(EnvAuditReport $report, OutputStyle $output): void
    {
        if ($report->skippedFiles === 0) {
            return;
        }

        $output->writeln(sprintf('  <fg=yellow>⚠ %d file(s) skipped due to parse errors and excluded from the audit</>', $report->skippedFiles));
        $output->newLine();
    }

    private function relativePath(string $absolute): string
    {
        $cwd = getcwd();

        if ($cwd && str_starts_with($absolute, $cwd.'/')) {
            return substr($absolute, strlen($cwd) + 1);
        }

        return $absolute;
    }
}
