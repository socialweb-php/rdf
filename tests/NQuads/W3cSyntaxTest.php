<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\Exception\MalformedNQuads;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Test\Rdf\TestCase;

use function explode;
use function iterator_count;
use function ltrim;
use function str_replace;
use function str_starts_with;

/**
 * Runs the W3C RDF 1.1 N-Quads syntax test suite
 *
 * A positive syntax test passes if the input parses; a negative syntax test
 * passes if the parser raises MalformedNQuads.
 *
 * @link https://w3c.github.io/rdf-tests/rdf/rdf11/rdf-n-quads/
 */
class W3cSyntaxTest extends TestCase
{
    #[DataProvider('positiveSyntaxTests')]
    public function testPositiveSyntax(string $path): void
    {
        $document = W3cManifest::read($path);

        $quads = iterator_count((new Parser())->parseQuads($document));

        $this->assertSame(self::countStatementLines($document), $quads);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function positiveSyntaxTests(): iterable
    {
        return W3cManifest::entries(W3cManifest::POSITIVE);
    }

    #[DataProvider('negativeSyntaxTests')]
    public function testNegativeSyntax(string $path): void
    {
        $document = W3cManifest::read($path);

        $this->expectException(MalformedNQuads::class);

        (new Parser())->parse($document);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function negativeSyntaxTests(): iterable
    {
        return W3cManifest::entries(W3cManifest::NEGATIVE);
    }

    /**
     * Counts the lines that hold a statement: every line that is not blank
     * and not a comment. The suite's positive inputs put one statement per
     * such line and never repeat a statement.
     */
    private static function countStatementLines(string $document): int
    {
        $count = 0;

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $document)) as $line) {
            $line = ltrim($line, " \t");

            if ($line !== '' && !str_starts_with($line, '#')) {
                $count++;
            }
        }

        return $count;
    }
}
