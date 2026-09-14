<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\NQuads\Grammar;
use SocialWeb\Rdf\NQuads\Scanner;
use SocialWeb\Test\Rdf\TestCase;

class ScannerTest extends TestCase
{
    public function testStartsAtTheBeginningOfTheLine(): void
    {
        $scanner = new Scanner('<a:b> .', 1);

        $this->assertSame(0, $scanner->position());
        $this->assertSame('<', $scanner->peek());
        $this->assertFalse($scanner->atEnd());
    }

    public function testPeekReturnsAnEmptyStringAtTheEndOfTheLine(): void
    {
        $scanner = new Scanner('', 1);

        $this->assertSame('', $scanner->peek());
        $this->assertTrue($scanner->atEnd());
    }

    public function testAtEndIsTrueAtTheStartOfAComment(): void
    {
        $scanner = new Scanner('# comment', 1);

        $this->assertTrue($scanner->atEnd());
        $this->assertSame('#', $scanner->peek());
    }

    public function testSkipWhitespaceAdvancesPastSpacesAndTabs(): void
    {
        $scanner = new Scanner(" \t \tx", 1);
        $scanner->skipWhitespace();

        $this->assertSame(4, $scanner->position());
        $this->assertSame('x', $scanner->peek());
    }

    public function testSkipWhitespaceLeavesOtherCharactersAlone(): void
    {
        $scanner = new Scanner("\u{00A0}x", 1);
        $scanner->skipWhitespace();

        $this->assertSame(0, $scanner->position());
    }

    public function testAcceptAdvancesPastMatchingText(): void
    {
        $scanner = new Scanner('^^<a:b>', 1);

        $this->assertTrue($scanner->accept('^^'));
        $this->assertSame(2, $scanner->position());
        $this->assertSame('<', $scanner->peek());
    }

    public function testAcceptLeavesThePositionWhenTheTextDoesNotMatch(): void
    {
        $scanner = new Scanner('^<a:b>', 1);

        $this->assertFalse($scanner->accept('^^'));
        $this->assertSame(0, $scanner->position());
    }

    public function testTakeReturnsTheCapturedGroupAndAdvancesPastTheWholeMatch(): void
    {
        $scanner = new Scanner('_:b0 <a:b>', 1);

        $this->assertSame('b0', $scanner->take(Grammar::BLANK_NODE_LABEL_TOKEN));
        $this->assertSame(4, $scanner->position());
        $this->assertSame(' ', $scanner->peek());
    }

    public function testTakeMatchesOnlyAtTheCurrentPosition(): void
    {
        $scanner = new Scanner(' _:b0', 1);

        $this->assertNull($scanner->take(Grammar::BLANK_NODE_LABEL_TOKEN));
        $this->assertSame(0, $scanner->position());
    }

    #[DataProvider('iriRefs')]
    public function testTakeIriRefReturnsTheBodyAndAdvancesPastTheClosingBracket(
        string $line,
        ?string $expected,
        int $expectedPosition,
    ): void {
        $scanner = new Scanner($line, 1);

        $this->assertSame($expected, $scanner->takeIriRef());
        $this->assertSame($expectedPosition, $scanner->position());
    }

    /**
     * @return iterable<string, array{string, string | null, int}>
     */
    public static function iriRefs(): iterable
    {
        yield 'at the start' => ['<http://a/b> .', 'http://a/b', 12];
        yield 'not at the offset' => [' <http://a/b>', null, 0];
        yield 'keeps escapes encoded' => ['<http://a/\u00E9>', 'http://a/\u00E9', 17];
        yield 'with a long escape' => ['<http://a/\U0001F600>', 'http://a/\U0001F600', 21];
        yield 'with an escape at the end' => ['<http://a/\U0001F600>x', 'http://a/\U0001F600', 21];
        yield 'may be empty' => ['<>', '', 2];
        yield 'with non-ASCII' => ['<http://a/é>', 'http://a/é', 13];
        yield 'rejects a space' => ['<http://a/ b>', null, 0];
        yield 'rejects a bad escape' => ['<http://a/\u00ZZ>', null, 0];
        yield 'rejects a short long escape' => ['<http://a/\U0000WXYZ>', null, 0];
        yield 'rejects an ECHAR' => ['<http://a/\n>', null, 0];
        yield 'rejects an unterminated reference' => ['<http://a/b', null, 0];
        yield 'rejects nothing after the opening bracket' => ['<', null, 0];
        yield 'rejects a nested opening bracket' => ['<http://a/<b>', null, 0];
        yield 'rejects a quotation mark' => ['<http://a/"b>', null, 0];
        yield 'rejects a left brace' => ['<http://a/{b>', null, 0];
        yield 'rejects a right brace' => ['<http://a/}b>', null, 0];
        yield 'rejects a pipe' => ['<http://a/|b>', null, 0];
        yield 'rejects a caret' => ['<http://a/^b>', null, 0];
        yield 'rejects a backtick' => ['<http://a/`b>', null, 0];
        yield 'rejects a tab' => ["<http://a/\tb>", null, 0];
        yield 'rejects a null byte' => ["<http://a/\x00b>", null, 0];
        yield 'rejects a unit separator' => ["<http://a/\x1fb>", null, 0];
    }

    public function testTakeIriRefMatchesAtTheCurrentOffset(): void
    {
        $scanner = new Scanner(' <http://a/b> .', 1);
        $scanner->skipWhitespace();

        $this->assertSame('http://a/b', $scanner->takeIriRef());
        $this->assertSame(13, $scanner->position());
    }

    #[DataProvider('stringLiterals')]
    public function testTakeStringLiteralQuoteReturnsTheBodyAndAdvancesPastTheClosingQuote(
        string $line,
        ?string $expected,
        int $expectedPosition,
    ): void {
        $scanner = new Scanner($line, 1);

        $this->assertSame($expected, $scanner->takeStringLiteralQuote());
        $this->assertSame($expectedPosition, $scanner->position());
    }

    /**
     * @return iterable<string, array{string, string | null, int}>
     */
    public static function stringLiterals(): iterable
    {
        yield 'at the start' => ['"abc" .', 'abc', 5];
        yield 'not at the offset' => [' "abc"', null, 0];
        yield 'may be empty' => ['""', '', 2];
        yield 'keeps escapes encoded' => ['"a\"b\\\\cé"', 'a\"b\\\\cé', 11];
        yield 'keeps a UCHAR encoded' => ['"a\u00E9b"', 'a\u00E9b', 10];
        yield 'keeps a long UCHAR encoded' => ['"\U0001F600"', '\U0001F600', 12];
        yield 'allows a raw tab' => ["\"a\tb\"", "a\tb", 5];
        yield 'ends at the first unescaped quote' => ['"a"b"', 'a', 3];
        yield 'rejects a bad escape' => ['"a\zb"', null, 0];
        yield 'rejects a bad UCHAR' => ['"\uWXYZ"', null, 0];
        yield 'rejects a raw line feed' => ["\"a\nb\"", null, 0];
        yield 'rejects a raw carriage return' => ["\"a\rb\"", null, 0];
        yield 'rejects an unterminated literal' => ['"abc', null, 0];
        yield 'rejects nothing after the opening quote' => ['"', null, 0];
        yield 'rejects an escaped closing quote' => ['"abc\"', null, 0];
    }

    public function testTakeStringLiteralQuoteMatchesAtTheCurrentOffset(): void
    {
        $scanner = new Scanner(' "abc" .', 1);
        $scanner->skipWhitespace();

        $this->assertSame('abc', $scanner->takeStringLiteralQuote());
        $this->assertSame(6, $scanner->position());
    }

    public function testErrorReportsTheLineNumberAndTheCurrentColumn(): void
    {
        $scanner = new Scanner('<a:b> x', 7);
        $scanner->takeIriRef();
        $scanner->skipWhitespace();

        $error = $scanner->error('unexpected x');

        $this->assertSame(7, $error->lineNumber);
        $this->assertSame(7, $error->columnNumber);
        $this->assertSame('<a:b> x', $error->offendingLine);
        $this->assertNull($error->getPrevious());
        $this->assertSame(
            'Malformed N-Quads at line 7, column 7: unexpected x. Offending line: <a:b> x',
            $error->getMessage(),
        );
    }

    public function testErrorCountsColumnsInCodePointsNotBytes(): void
    {
        // "é" is 2 bytes, "€" is 3, and "😀" is 4; the "x" is the 9th code point.
        $scanner = new Scanner('<a:é€😀> x', 1);
        $scanner->takeIriRef();
        $scanner->skipWhitespace();

        $this->assertSame(14, $scanner->position());
        $this->assertSame(9, $scanner->error('unexpected x')->columnNumber);
    }

    public function testErrorReportsAnExplicitOffsetAndAPreviousThrowable(): void
    {
        $scanner = new Scanner('<a:b> <c:d>', 1);
        $scanner->takeIriRef();
        $scanner->skipWhitespace();
        $scanner->takeIriRef();
        $previous = new InvalidArgument('cause');

        $error = $scanner->error('bad', 6, $previous);

        $this->assertSame(7, $error->columnNumber);
        $this->assertSame($previous, $error->getPrevious());
    }

    public function testErrorAtTheStartOfTheLineIsColumnOne(): void
    {
        $scanner = new Scanner('x', 3);

        $this->assertSame(1, $scanner->error('bad')->columnNumber);
    }
}
