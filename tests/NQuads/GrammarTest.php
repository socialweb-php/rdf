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
        yield 'colon inside' => ['a:b'];
        yield 'leading colon' => [':b'];
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

    #[DataProvider('tokens')]
    public function testTokenPatternsMatchOnlyAtTheOffset(
        string $pattern,
        string $subject,
        int $offset,
        ?string $expected,
    ): void {
        $result = preg_match($pattern, $subject, $matches, 0, $offset);

        if ($expected === null) {
            // preg_match() returns 0 for no match and false for invalid UTF-8.
            $this->assertNotSame(1, $result);

            return;
        }

        $this->assertSame(1, $result);
        $this->assertArrayHasKey(1, $matches);
        $this->assertSame($expected, $matches[1]);
    }

    /**
     * @return iterable<string, array{string, string, int, string | null}>
     */
    public static function tokens(): iterable
    {
        yield 'IRIREF at the start' => [Grammar::IRIREF_TOKEN, '<http://a/b> .', 0, 'http://a/b'];
        yield 'IRIREF at an offset' => [Grammar::IRIREF_TOKEN, ' <http://a/b>', 1, 'http://a/b'];
        yield 'IRIREF not at the offset' => [Grammar::IRIREF_TOKEN, ' <http://a/b>', 0, null];
        yield 'IRIREF keeps escapes encoded' => [Grammar::IRIREF_TOKEN, '<http://a/\u00E9>', 0, 'http://a/\u00E9'];
        yield 'IRIREF with a long escape' => [Grammar::IRIREF_TOKEN, '<http://a/\U0001F600>', 0, 'http://a/\U0001F600'];
        yield 'IRIREF may be empty' => [Grammar::IRIREF_TOKEN, '<>', 0, ''];
        yield 'IRIREF with non-ASCII' => [Grammar::IRIREF_TOKEN, '<http://a/é>', 0, 'http://a/é'];
        yield 'IRIREF rejects a space' => [Grammar::IRIREF_TOKEN, '<http://a/ b>', 0, null];
        yield 'IRIREF rejects a bad escape' => [Grammar::IRIREF_TOKEN, '<http://a/\u00ZZ>', 0, null];
        yield 'IRIREF rejects a short long escape' => [Grammar::IRIREF_TOKEN, '<http://a/\U0000WXYZ>', 0, null];
        yield 'IRIREF rejects an ECHAR' => [Grammar::IRIREF_TOKEN, '<http://a/\n>', 0, null];
        yield 'IRIREF rejects an unterminated reference' => [Grammar::IRIREF_TOKEN, '<http://a/b', 0, null];
        yield 'IRIREF rejects invalid UTF-8' => [Grammar::IRIREF_TOKEN, "<http://a/\xff>", 0, null];
        yield 'label at the start' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_:b0 .', 0, 'b0'];
        yield 'label stops before a trailing dot' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_:b0.', 0, 'b0'];
        yield 'label may contain a dot' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_:a.b', 0, 'a.b'];
        yield 'label may start with a digit' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_:1a', 0, '1a'];
        yield 'label stops before a colon' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_:abc:def', 0, 'abc'];
        yield 'label rejects a leading colon' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_::a', 0, null];
        yield 'label rejects a leading hyphen' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_:-a', 0, null];
        yield 'label must not be empty' => [Grammar::BLANK_NODE_LABEL_TOKEN, '_: .', 0, null];
        yield 'string at the start' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '"abc" .', 0, 'abc'];
        yield 'string may be empty' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '""', 0, ''];
        yield 'string keeps escapes encoded' => [
            Grammar::STRING_LITERAL_QUOTE_TOKEN,
            '"a\"b\\\\cé"',
            0,
            'a\"b\\\\cé',
        ];
        yield 'string ends at the first unescaped quote' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '"a"b"', 0, 'a'];
        yield 'string rejects a bad escape' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '"a\zb"', 0, null];
        yield 'string rejects a bad UCHAR' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '"\uWXYZ"', 0, null];
        yield 'string rejects a raw line feed' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, "\"a\nb\"", 0, null];
        yield 'string rejects a raw carriage return' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, "\"a\rb\"", 0, null];
        yield 'string rejects an unterminated literal' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '"abc', 0, null];
        yield 'string rejects an escaped closing quote' => [Grammar::STRING_LITERAL_QUOTE_TOKEN, '"abc\"', 0, null];
        yield 'language tag' => [Grammar::LANGTAG_TOKEN, '@en .', 0, 'en'];
        yield 'language tag with subtags' => [Grammar::LANGTAG_TOKEN, '@zh-Hant-TW', 0, 'zh-Hant-TW'];
        yield 'language tag stops before a trailing hyphen' => [Grammar::LANGTAG_TOKEN, '@en-', 0, 'en'];
        yield 'language tag rejects a leading digit' => [Grammar::LANGTAG_TOKEN, '@1', 0, null];
        yield 'language tag requires the at sign' => [Grammar::LANGTAG_TOKEN, 'en', 0, null];
    }
}
