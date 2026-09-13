<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\NQuads\Grammar;
use SocialWeb\Test\Rdf\TestCase;

use function preg_match;

class GrammarTest extends TestCase
{
    #[DataProvider('validBlankNodeLabels')]
    public function testBlankNodeLabelAcceptsValidLabels(string $label): void
    {
        $this->assertSame(1, preg_match(Grammar::BLANK_NODE_LABEL, $label));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validBlankNodeLabels(): iterable
    {
        yield 'single letter' => ['b'];
        yield 'single digit' => ['0'];
        yield 'letters and digits' => ['b0'];
        yield 'canonical label' => ['c14n0'];
        yield 'leading underscore' => ['_b'];
        yield 'colon inside' => ['a:b'];
        yield 'leading colon' => [':b'];
        yield 'hyphen inside' => ['a-b'];
        yield 'dot inside' => ['a.b'];
        yield 'non-ASCII letters' => ['état'];
        yield 'middle dot' => ['a·b'];
        yield 'supplementary plane character' => ['a𐀀b'];
    }

    #[DataProvider('invalidBlankNodeLabels')]
    public function testBlankNodeLabelRejectsInvalidLabels(string $label): void
    {
        $this->assertNotSame(1, preg_match(Grammar::BLANK_NODE_LABEL, $label));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBlankNodeLabels(): iterable
    {
        yield 'empty' => [''];
        yield 'leading hyphen' => ['-b'];
        yield 'leading dot' => ['.b'];
        yield 'trailing dot' => ['b.'];
        yield 'space' => ['a b'];
        yield 'trailing newline' => ["b0\n"];
        yield 'invalid UTF-8' => ["b\xff"];
    }

    #[DataProvider('validLanguageTags')]
    public function testLangtagAcceptsWellFormedTags(string $tag): void
    {
        $this->assertSame(1, preg_match(Grammar::LANGTAG, $tag));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validLanguageTags(): iterable
    {
        yield 'two letters' => ['en'];
        yield 'with region' => ['en-US'];
        yield 'with script and region' => ['zh-Hant-TW'];
        yield 'with numeric subtag' => ['es-419'];
        yield 'uppercase' => ['EN'];
    }

    #[DataProvider('invalidLanguageTags')]
    public function testLangtagRejectsMalformedTags(string $tag): void
    {
        $this->assertNotSame(1, preg_match(Grammar::LANGTAG, $tag));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLanguageTags(): iterable
    {
        yield 'empty' => [''];
        yield 'leading digit' => ['1en'];
        yield 'underscore' => ['en_US'];
        yield 'trailing hyphen' => ['en-'];
        yield 'double hyphen' => ['en--US'];
        yield 'space' => ['en US'];
        yield 'trailing newline' => ["en\n"];
    }

    #[DataProvider('forbiddenIriCharacters')]
    public function testIrirefForbiddenMatchesForbiddenCharacters(string $iri): void
    {
        $this->assertSame(1, preg_match(Grammar::IRIREF_FORBIDDEN, $iri));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenIriCharacters(): iterable
    {
        yield 'space' => ['http://example.com/a b'];
        yield 'less than' => ['http://example.com/<'];
        yield 'greater than' => ['http://example.com/>'];
        yield 'double quote' => ['http://example.com/"'];
        yield 'left brace' => ['http://example.com/{'];
        yield 'right brace' => ['http://example.com/}'];
        yield 'pipe' => ['http://example.com/|'];
        yield 'caret' => ['http://example.com/^'];
        yield 'backtick' => ['http://example.com/`'];
        yield 'backslash' => ['http://example.com/\\'];
        yield 'null' => ["http://example.com/\x00"];
        yield 'tab' => ["http://example.com/\t"];
        yield 'unit separator U+001F' => ["http://example.com/\x1f"];
    }

    public function testIrirefForbiddenDoesNotMatchAllowedCharacters(): void
    {
        $allowed = 'http://example.com/path?q=1&r=2#frag~!*\'();:@=+$,%[]';

        $this->assertSame(0, preg_match(Grammar::IRIREF_FORBIDDEN, $allowed));
        $this->assertSame(0, preg_match(Grammar::IRIREF_FORBIDDEN, 'http://example.com/café'));
    }

    #[DataProvider('schemes')]
    public function testSchemeMatchesAbsoluteIrisOnly(string $iri, int $expected): void
    {
        $this->assertSame($expected, preg_match(Grammar::SCHEME, $iri));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function schemes(): iterable
    {
        yield 'http' => ['http://example.com/', 1];
        yield 'urn' => ['urn:isbn:0451450523', 1];
        yield 'scheme with plus, dot, and hyphen' => ['svn+ssh://example.com/', 1];
        yield 'single letter scheme' => ['a:', 1];
        yield 'relative path' => ['path/to', 0];
        yield 'absolute path' => ['/path', 0];
        yield 'leading digit' => ['1a:b', 0];
        yield 'empty' => ['', 0];
    }
}
