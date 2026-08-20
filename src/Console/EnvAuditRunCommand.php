<?php

namespace Phoenix1331\LaravelEnvAudit\Console;

use Illuminate\Console\Command;
use Phoenix1331\LaravelEnvAudit\Data\EnvAuditReport;
use Phoenix1331\LaravelEnvAudit\Formatters\ConsoleFormatter;
use Phoenix1331\LaravelEnvAudit\Formatters\HtmlFormatter;
use Phoenix1331\LaravelEnvAudit\Formatters\JsonFormatter;
use Phoenix1331\LaravelEnvAudit\Scanning\AttributeResolver;
use Phoenix1331\LaravelEnvAudit\Scanning\ConfigUsageResolver;
use Phoenix1331\LaravelEnvAudit\Scanning\EnvFileParser;
use Phoenix1331\LaravelEnvAudit\Scanning\EnvUsageScanner;
use Phoenix1331\LaravelEnvAudit\Scanning\SecretHeuristicDetector;

class EnvAuditRunCommand extends Command
{
    protected $signature = 'env-audit:run
                            {--json : Output results as JSON}
                            {--html= : Write an HTML report to the given path}
                            {--fail-on= : Comma-separated violation categories that cause a non-zero exit code}';

    protected $description = 'Audit env() usage, .env.example drift, and possible secrets';

    public function handle(
        EnvUsageScanner $scanner,
        EnvFileParser $parser,
        ConfigUsageResolver $resolver,
        AttributeResolver $attributeResolver,
    ): int {
        if (! config('env-audit.enabled', true)) {
            $this->info('env-audit is disabled via config.');

            return self::SUCCESS;
        }

        $scanPaths = config('env-audit.scan_paths', []);
        $ignorePaths = config('env-audit.ignore_paths', []);
        $exampleFile = config('env-audit.example_file', base_path('.env.example'));
        $configPath = config('env-audit.config_path', config_path());

        // Scan env() call sites
        $allCalls = $scanner->scan($scanPaths, $ignorePaths, $configPath);

        // Parse .env.example (key => value, values for heuristic use only)
        $exampleEntries = $parser->parseExample($exampleFile);

        // Resolve ignore attributes and inline comments across all scanned PHP files
        $phpFiles = $this->collectPhpFiles($scanPaths, $ignorePaths);
        $allIgnores = $attributeResolver->resolve($phpFiles);

        // Direct usage violations: env() calls outside config/ not covered by an ignore
        $directViolations = array_values(array_filter(
            $allCalls,
            fn ($call) => ! $call->inConfig && ! $attributeResolver->isCovered($call->file, $call->line, $allIgnores)
        ));

        // Drift detection
        $usedInConfig = $resolver->keysUsedInConfig($allCalls);
        $missingFromExample = $resolver->missingFromExample($usedInConfig, $exampleEntries);
        $unusedInExample = $resolver->unusedInExample($exampleEntries, $allCalls);

        // Secret heuristic on .env.example values only
        $possibleSecrets = [];

        if (config('env-audit.secret_heuristics.enabled', true)) {
            $extraPatterns = config('env-audit.secret_heuristics.patterns', []);
            $detector = new SecretHeuristicDetector($extraPatterns);
            $possibleSecrets = $detector->detect($exampleEntries);
        }

        // Assemble report
        $report = EnvAuditReport::build(
            allCalls: $allCalls,
            directViolations: $directViolations,
            missingFromExample: $missingFromExample,
            unusedInExample: $unusedInExample,
            possibleSecrets: $possibleSecrets,
            allIgnores: $allIgnores,
        );

        // Output
        if ($this->option('json')) {
            $this->line((new JsonFormatter)->format($report));
        } else {
            (new ConsoleFormatter)->write($report, $this->output);
        }

        // HTML report
        $htmlPath = $this->option('html') ?? config('env-audit.html.output_path');

        if ($this->option('html')) {
            $this->writeHtmlReport($report, (string) $htmlPath);
        }

        // Determine exit code
        $failOn = $this->resolveFailOn();

        if ($report->hasViolationsIn($failOn)) {
            if (! $this->option('json')) {
                $this->writeFailureSummary($report, $failOn);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveFailOn(): array
    {
        $cliValue = $this->option('fail-on');

        if ($cliValue !== null) {
            return array_map('trim', explode(',', (string) $cliValue));
        }

        return config('env-audit.fail_on', ['direct-usage', 'possible-secret']);
    }

    private function writeHtmlReport(EnvAuditReport $report, string $path): void
    {
        $title = config('env-audit.html.title', 'Env Audit Report');
        $html = (new HtmlFormatter((string) $title))->format($report);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $html);
        $this->info("HTML report written to {$path}");
    }

    private function writeFailureSummary(EnvAuditReport $report, array $failOn): void
    {
        $this->newLine();
        $failing = array_filter($failOn, fn ($cat) => $report->countFor($cat) > 0);
        $this->error(sprintf(
            'Failing: %d error-level finding(s) in: %s',
            array_sum(array_map(fn ($cat) => $report->countFor($cat), $failing)),
            implode(', ', $failing),
        ));
    }

    /** @return array<string> */
    private function collectPhpFiles(array $scanPaths, array $ignorePaths): array
    {
        $files = [];

        foreach ($scanPaths as $path) {
            if (is_file($path) && str_ends_with($path, '.php')) {
                $files[] = $path;

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $realPath = $file->getRealPath();

                foreach ($ignorePaths as $ignore) {
                    if (str_starts_with($realPath.'/', rtrim($ignore, '/').'/')) {
                        continue 2;
                    }
                }

                $files[] = $realPath;
            }
        }

        return $files;
    }
}
