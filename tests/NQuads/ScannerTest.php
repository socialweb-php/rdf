<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

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
        $scanner = new Scanner('<http://example.com/> <a:b>', 1);

        $this->assertSame('http://example.com/', $scanner->take(Grammar::IRIREF_TOKEN));
        $this->assertSame(21, $scanner->position());
        $this->assertSame(' ', $scanner->peek());
    }

    public function testTakeMatchesOnlyAtTheCurrentPosition(): void
    {
        $scanner = new Scanner(' <http://example.com/>', 1);

        $this->assertNull($scanner->take(Grammar::IRIREF_TOKEN));
        $this->assertSame(0, $scanner->position());
    }

    public function testErrorReportsTheLineNumberAndTheCurrentColumn(): void
    {
        $scanner = new Scanner('<a:b> x', 7);
        $scanner->take(Grammar::IRIREF_TOKEN);
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
        $scanner->take(Grammar::IRIREF_TOKEN);
        $scanner->skipWhitespace();

        $this->assertSame(14, $scanner->position());
        $this->assertSame(9, $scanner->error('unexpected x')->columnNumber);
    }

    public function testErrorReportsAnExplicitOffsetAndAPreviousThrowable(): void
    {
        $scanner = new Scanner('<a:b> <c:d>', 1);
        $scanner->take(Grammar::IRIREF_TOKEN);
        $scanner->skipWhitespace();
        $scanner->take(Grammar::IRIREF_TOKEN);
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
