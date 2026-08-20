<?php

namespace Phoenix1331\LaravelEnvAudit\Scanning;

use Phoenix1331\LaravelEnvAudit\Data\IgnoreEntry;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class AttributeResolver
{
    private Parser $parser;

    private string $today;

    public function __construct(?string $today = null)
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->today = $today ?? date('Y-m-d');
    }

    /**
     * Resolve all #[WithoutEnvAudit] attributes and // env-audit-ignore: comments
     * from the given PHP files.
     *
     * @param  array<string>  $files
     * @return array<IgnoreEntry>
     */
    public function resolve(array $files): array
    {
        $entries = [];

        foreach ($files as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $source = file_get_contents($file);

            if ($source === false) {
                continue;
            }

            try {
                $ast = $this->parser->parse($source);
            } catch (\Throwable) {
                continue;
            }

            if ($ast === null) {
                continue;
            }

            $entries = array_merge(
                $entries,
                $this->extractAttributes($ast, $file),
                $this->extractInlineComments($ast, $file),
            );
        }

        return $entries;
    }

    /**
     * Determine whether a given file+line is covered by any ignore entry.
     * Expired ignores are NOT treated as active covers.
     *
     * @param  array<IgnoreEntry>  $entries
     */
    public function isCovered(string $file, int $line, array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($entry->expired) {
                continue;
            }

            if ($entry->file !== $file) {
                continue;
            }

            // Attribute on a class/method covers all lines in that declaration.
            // For inline comments the entry line matches the env() call line.
            // The comment is attached to the statement node it precedes.
            // Accept a match on the same line or the immediately following line.
            if ($entry->source === 'inline-comment' && abs($entry->line - $line) <= 1) {
                return true;
            }

            if ($entry->source === 'attribute') {
                // Attribute line is the start of the attributed node; we accept
                // any env() call on or after that line in the same file as covered.
                // A more precise range would require storing end lines — acceptable
                // for v1 where attributes are on the class/method containing the call.
                if ($line >= $entry->line) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<IgnoreEntry>
     */
    private function extractAttributes(array $ast, string $file): array
    {
        $entries = [];
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class($file, $entries, $this->today) extends NodeVisitorAbstract
        {
            /** @param array<IgnoreEntry> $entries */
            public function __construct(
                private readonly string $file,
                private array &$entries,
                private readonly string $today,
            ) {}

            public function enterNode(Node $node): null
            {
                if (! $node instanceof Class_ && ! $node instanceof ClassMethod && ! $node instanceof Function_) {
                    return null;
                }

                foreach ($node->attrGroups as $attrGroup) {
                    foreach ($attrGroup->attrs as $attr) {
                        $name = (string) $attr->name;

                        if (! in_array($name, ['WithoutEnvAudit', 'Phoenix1331\\LaravelEnvAudit\\Attributes\\WithoutEnvAudit'], true)) {
                            continue;
                        }

                        $reason = '';
                        $expires = null;

                        foreach ($attr->args as $arg) {
                            $argName = $arg->name?->name;
                            $value = $arg->value;

                            if (($argName === 'reason' || $argName === null) && $value instanceof String_) {
                                $reason = $value->value;
                            }

                            if ($argName === 'expires' && $value instanceof String_) {
                                $expires = $value->value;
                            }
                        }

                        $expired = $expires !== null && $expires < $this->today;

                        $this->entries[] = new IgnoreEntry(
                            file: $this->file,
                            line: $node->getStartLine(),
                            reason: $reason,
                            expires: $expires,
                            expired: $expired,
                            source: 'attribute',
                        );
                    }
                }

                return null;
            }
        });

        $traverser->traverse($ast);

        return $entries;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<IgnoreEntry>
     */
    private function extractInlineComments(array $ast, string $file): array
    {
        // php-parser attaches comments to the statement node, not the expression.
        // We walk every node, collect any env-audit-ignore comment, and record the
        // line of the *node* the comment is attached to. isCovered() then matches
        // env() calls on the same line or the immediately following line.
        $entries = [];
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class($file, $entries) extends NodeVisitorAbstract
        {
            /** @param array<IgnoreEntry> $entries */
            public function __construct(
                private readonly string $file,
                private array &$entries,
            ) {}

            public function enterNode(Node $node): null
            {
                $comments = $node->getAttribute('comments') ?? [];

                foreach ($comments as $comment) {
                    $text = $comment->getText();

                    if (! str_contains($text, 'env-audit-ignore:')) {
                        continue;
                    }

                    $reason = trim(explode('env-audit-ignore:', $text, 2)[1]);
                    $reason = rtrim($reason, " \t*/");

                    $this->entries[] = new IgnoreEntry(
                        file: $this->file,
                        line: $node->getStartLine(),
                        reason: $reason,
                        expires: null,
                        expired: false,
                        source: 'inline-comment',
                    );
                }

                return null;
            }
        });

        $traverser->traverse($ast);

        return $entries;
    }
}
