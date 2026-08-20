<?php

namespace Phoenix1331\LaravelEnvAudit\Scanning;

use Phoenix1331\LaravelEnvAudit\Data\EnvCall;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class EnvUsageScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * Scan all PHP files under the given paths and return every env() call found.
     *
     * @param  array<string>  $scanPaths
     * @param  array<string>  $ignorePaths
     * @param  string  $configPath  Absolute path to the config/ directory
     * @return array<EnvCall>
     */
    public function scan(array $scanPaths, array $ignorePaths, string $configPath): array
    {
        $calls = [];

        foreach ($scanPaths as $path) {
            if (! is_dir($path) && ! is_file($path)) {
                continue;
            }

            foreach ($this->phpFiles($path) as $file) {
                if ($this->isIgnored($file, $ignorePaths)) {
                    continue;
                }

                $inConfig = $this->isInsideConfig($file, $configPath);
                $calls = array_merge($calls, $this->scanFile($file, $inConfig));
            }
        }

        return $calls;
    }

    /**
     * @return array<EnvCall>
     */
    private function scanFile(string $filePath, bool $inConfig): array
    {
        $source = @file_get_contents($filePath);

        if ($source === false) {
            return [];
        }

        try {
            $ast = $this->parser->parse($source);
        } catch (\Throwable) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $calls = [];
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class($filePath, $inConfig, $calls) extends NodeVisitorAbstract
        {
            /** @param  array<EnvCall>  $calls */
            public function __construct(
                private readonly string $filePath,
                private readonly bool $inConfig,
                private array &$calls,
            ) {}

            public function enterNode(Node $node): null
            {
                if (! $node instanceof FuncCall) {
                    return null;
                }

                if (! $node->name instanceof Name) {
                    return null;
                }

                if (strtolower((string) $node->name) !== 'env') {
                    return null;
                }

                $key = null;
                if (isset($node->args[0]) && $node->args[0]->value instanceof String_) {
                    $key = $node->args[0]->value->value;
                }

                $this->calls[] = new EnvCall(
                    file: $this->filePath,
                    line: $node->getStartLine(),
                    key: $key,
                    inConfig: $this->inConfig,
                );

                return null;
            }
        });

        $traverser->traverse($ast);

        return $calls;
    }

    /**
     * Yield all .php files under a path (file or directory).
     *
     * @return iterable<string>
     */
    private function phpFiles(string $path): iterable
    {
        if (is_file($path)) {
            if (str_ends_with($path, '.php')) {
                yield $path;
            }

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                yield $file->getRealPath();
            }
        }
    }

    private function isIgnored(string $file, array $ignorePaths): bool
    {
        foreach ($ignorePaths as $ignore) {
            $ignore = rtrim($ignore, '/').'/';
            if (str_starts_with($file.'/', $ignore)) {
                return true;
            }
        }

        return false;
    }

    private function isInsideConfig(string $file, string $configPath): bool
    {
        $configPath = rtrim($configPath, '/').'/';

        return str_starts_with($file.'/', $configPath);
    }
}
