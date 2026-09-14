<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\DefaultGraph;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Exception\MalformedNQuads;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Rdf\Quad;
use SocialWeb\Test\Rdf\TestCase;

use function iterator_to_array;

class ParserTest extends TestCase
{
    #[DataProvider('documentsWithoutStatements')]
    public function testParsesADocumentWithoutStatementsIntoAnEmptyDataset(string $document): void
    {
        $this->assertCount(0, (new Parser())->parse($document));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function documentsWithoutStatements(): iterable
    {
        yield 'empty' => [''];
        yield 'one line feed' => ["\n"];
        yield 'blank lines' => ["\n\n\r\n\r"];
        yield 'whitespace only' => [" \t \n\t"];
        yield 'comment only' => ['# a comment'];
        yield 'comment after whitespace' => ["  \t # a comment\n"];
        yield 'several comments' => ["# one\n# two\n\n# three"];
    }

    public function testParsesATripleIntoTheDefaultGraph(): void
    {
        $quads = (new Parser())->parse('<http://a/s> <http://a/p> <http://a/o> .')->toArray();

        $this->assertCount(1, $quads);
        $this->assertTrue(new Quad(
            new Iri('http://a/s'),
            new Iri('http://a/p'),
            new Iri('http://a/o'),
        )->equals($quads[0]));
        $this->assertInstanceOf(DefaultGraph::class, $quads[0]->graph);
    }

    public function testParsesAQuadWithAnIriGraphLabel(): void
    {
        $quads = (new Parser())->parse('<http://a/s> <http://a/p> <http://a/o> <http://a/g> .')->toArray();

        $this->assertTrue(new Quad(
            new Iri('http://a/s'),
            new Iri('http://a/p'),
            new Iri('http://a/o'),
            new Iri('http://a/g'),
        )->equals($quads[0]));
    }

    public function testParsesAQuadWithABlankNodeGraphLabel(): void
    {
        $quads = (new Parser())->parse('<http://a/s> <http://a/p> <http://a/o> _:g .')->toArray();

        $this->assertTrue(new Quad(
            new Iri('http://a/s'),
            new Iri('http://a/p'),
            new Iri('http://a/o'),
            new BlankNode('g'),
        )->equals($quads[0]));
    }

    #[DataProvider('blankNodeLabels')]
    public function testPreservesBlankNodeLabelsExactly(string $label): void
    {
        $quads = (new Parser())->parse("_:$label <http://a/p> _:$label .")->toArray();

        $this->assertInstanceOf(BlankNode::class, $quads[0]->subject);
        $this->assertSame($label, $quads[0]->subject->identifier);
        $this->assertInstanceOf(BlankNode::class, $quads[0]->object);
        $this->assertSame($label, $quads[0]->object->identifier);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankNodeLabels(): iterable
    {
        yield 'letters and digits' => ['b0'];
        yield 'leading digit' => ['1a'];
        yield 'inner dot' => ['a.b'];
        yield 'inner hyphen' => ['a-b'];
        yield 'underscore' => ['_x'];
        yield 'non-ASCII' => ['état'];
        yield 'mixed case' => ['B0'];
    }

    public function testDecodesEscapesInIris(): void
    {
        $quads = (new Parser())->parse(
            '<http://a/\u00E9> <http://a/\U0001F600> <http://a/\u00E9\u20AC> .',
        )->toArray();

        $this->assertTrue(new Quad(
            new Iri('http://a/é'),
            new Iri('http://a/😀'),
            new Iri('http://a/é€'),
        )->equals($quads[0]));
    }

    #[DataProvider('lineEndings')]
    public function testAcceptsEveryLineEnding(string $eol): void
    {
        $document = '<http://a/s> <http://a/p> <http://a/o1> .' . $eol
            . '<http://a/s> <http://a/p> <http://a/o2> .' . $eol
            . '<http://a/s> <http://a/p> <http://a/o3> .' . $eol;

        $this->assertCount(3, (new Parser())->parse($document));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function lineEndings(): iterable
    {
        yield 'line feed' => ["\n"];
        yield 'carriage return and line feed' => ["\r\n"];
        yield 'carriage return' => ["\r"];
    }

    public function testDoesNotRequireAFinalLineEnding(): void
    {
        $this->assertCount(1, (new Parser())->parse('<http://a/s> <http://a/p> <http://a/o> .'));
    }

    #[DataProvider('whitespaceVariations')]
    public function testAcceptsWhitespaceAndCommentsAroundTerms(string $document): void
    {
        $quads = (new Parser())->parse($document)->toArray();

        $this->assertCount(1, $quads);
        $this->assertTrue(new Quad(
            new Iri('http://a/s'),
            new Iri('http://a/p'),
            new Iri('http://a/o'),
            new BlankNode('g'),
        )->equals($quads[0]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function whitespaceVariations(): iterable
    {
        yield 'no whitespace' => ['<http://a/s><http://a/p><http://a/o>_:g.'];
        yield 'tabs' => ["<http://a/s>\t<http://a/p>\t<http://a/o>\t_:g\t."];
        yield 'many spaces' => ['<http://a/s>   <http://a/p>   <http://a/o>   _:g   .   '];
        yield 'leading whitespace' => [" \t <http://a/s> <http://a/p> <http://a/o> _:g ."];
        yield 'comment after the statement' => ['<http://a/s> <http://a/p> <http://a/o> _:g . # comment'];
        yield 'comment without a space' => ['<http://a/s> <http://a/p> <http://a/o> _:g .# comment'];
        yield 'comment on the previous and next lines' => [
            "# before\n<http://a/s> <http://a/p> <http://a/o> _:g .\n# after",
        ];
        yield 'blank lines around' => ["\n\n<http://a/s> <http://a/p> <http://a/o> _:g .\n\n"];
    }

    public function testBlankNodeLabelEndsBeforeTheStatementTerminator(): void
    {
        $quads = (new Parser())->parse('_:s <http://a/p> _:o.')->toArray();

        $this->assertTrue(new Quad(new BlankNode('s'), new Iri('http://a/p'), new BlankNode('o'))
            ->equals($quads[0]));
    }

    public function testParseCollapsesDuplicateStatements(): void
    {
        $document = "<http://a/s> <http://a/p> <http://a/o> .\n<http://a/s> <http://a/p> <http://a/o> .\n";

        $this->assertCount(1, (new Parser())->parse($document));
    }

    public function testParseQuadsYieldsEveryStatementInOrder(): void
    {
        $document = "<http://a/s> <http://a/p> <http://a/o2> .\n"
            . "<http://a/s> <http://a/p> <http://a/o1> .\n"
            . "<http://a/s> <http://a/p> <http://a/o2> .\n";

        $quads = iterator_to_array((new Parser())->parseQuads($document), false);

        $this->assertCount(3, $quads);
        $this->assertTrue($quads[0]->equals($quads[2]));
        $this->assertFalse($quads[0]->equals($quads[1]));
    }

    public function testParseQuadsYieldsStatementsBeforeReachingALaterError(): void
    {
        $quads = (new Parser())->parseQuads("<http://a/s> <http://a/p> <http://a/o> .\nnot a statement\n");

        $this->assertInstanceOf(Generator::class, $quads);
        $first = $quads->current();
        $this->assertInstanceOf(Quad::class, $first);
        $expected = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o'));
        $this->assertTrue($expected->equals($first));

        $this->expectException(MalformedNQuads::class);

        $quads->next();
    }

    #[DataProvider('malformedDocuments')]
    public function testRejectsMalformedDocuments(
        string $document,
        int $lineNumber,
        int $columnNumber,
        string $reason,
    ): void {
        try {
            (new Parser())->parse($document);
        } catch (MalformedNQuads $exception) {
            $this->assertSame($lineNumber, $exception->lineNumber, 'line number');
            $this->assertSame($columnNumber, $exception->columnNumber, 'column number');
            $this->assertStringContainsString($reason, $exception->getMessage());

            return;
        }

        $this->fail('Expected MalformedNQuads to be thrown');
    }

    /**
     * @return iterable<string, array{string, int, int, string}>
     */
    public static function malformedDocuments(): iterable
    {
        $subject = 'Expected an IRI or a blank node label as the subject';
        $predicate = 'Expected an IRI as the predicate';
        $object = 'Expected an IRI, a blank node label, or a literal as the object';
        $terminator = "Expected '.' to end the statement";
        $endOfLine = "Expected the end of the line after '.'";

        yield 'Turtle prefix directive' => ['@prefix : <http://a/> .', 1, 1, $subject];
        yield 'Turtle base directive' => ['@base <http://a/> .', 1, 1, $subject];
        yield 'literal subject' => ['"s" <http://a/p> <http://a/o> .', 1, 1, $subject];
        yield 'bare word subject' => ['s <http://a/p> <http://a/o> .', 1, 1, $subject];
        yield 'only a subject' => ['<http://a/s>', 1, 13, $predicate];
        yield 'literal predicate' => ['<http://a/s> "p" <http://a/o> .', 1, 14, $predicate];
        yield 'blank node predicate' => ['<http://a/s> _:p <http://a/o> .', 1, 14, $predicate];
        yield 'number object' => ['<http://a/s> <http://a/p> 1 .', 1, 27, $object];
        yield 'decimal object' => ['<http://a/s> <http://a/p> 1.0 .', 1, 27, $object];
        yield 'single-quoted object' => ["<http://a/s> <http://a/p> 'o' .", 1, 27, $object];
        yield 'long single-quoted object' => ["<http://a/s> <http://a/p> '''o''' .", 1, 27, $object];
        yield 'missing object' => ['<http://a/s> <http://a/p> .', 1, 27, $object];
        yield 'missing terminator' => ['<http://a/s> <http://a/p> <http://a/o>', 1, 39, $terminator];
        yield 'missing terminator after a graph label' => [
            '<http://a/s> <http://a/p> <http://a/o> _:g',
            1,
            43,
            $terminator,
        ];
        yield 'object list' => ['<http://a/s> <http://a/p> <http://a/o>, <http://a/o2> .', 1, 39, $terminator];
        yield 'predicate list' => [
            '<http://a/s> <http://a/p> <http://a/o>; <http://a/p2> <http://a/o2> .',
            1,
            39,
            $terminator,
        ];
        yield 'fifth term' => [
            '<http://a/s> <http://a/p> <http://a/o> <http://a/g> <http://a/n> .',
            1,
            53,
            $terminator,
        ];
        yield 'text after the terminator' => [
            '<http://a/s> <http://a/p> <http://a/o> . <http://a/s>',
            1,
            42,
            $endOfLine,
        ];
        yield 'two statements on one line' => [
            '<http://a/s> <http://a/p> <http://a/o> . <http://a/s> <http://a/p> <http://a/o> .',
            1,
            42,
            $endOfLine,
        ];
        yield 'two terminators' => ['<http://a/s> <http://a/p> <http://a/o> ..', 1, 41, $endOfLine];
        yield 'IRI with a space' => ['<http://a/ s> <http://a/p> <http://a/o> .', 1, 1, 'Malformed IRI reference'];
        yield 'IRI with a bad short escape' => [
            '<http://a/s> <http://a/p> <http://a/\u00ZZ> .',
            1,
            27,
            'Malformed IRI reference',
        ];
        yield 'IRI with a bad long escape' => [
            '<http://a/s> <http://a/p> <http://a/\U00ZZ1111> .',
            1,
            27,
            'Malformed IRI reference',
        ];
        yield 'IRI with an ECHAR' => ['<http://a/s> <http://a/p> <http://a/\n> .', 1, 27, 'Malformed IRI reference'];
        yield 'IRI with an escaped slash' => [
            '<http://a/s> <http://a/p> <http://a/\/> .',
            1,
            27,
            'Malformed IRI reference',
        ];
        yield 'unterminated IRI' => ['<http://a/s> <http://a/p> <http://a/o .', 1, 27, 'Malformed IRI reference'];
        yield 'relative subject' => ['<s> <http://a/p> <http://a/o> .', 1, 1, 'An IRI must be absolute'];
        yield 'relative predicate' => ['<http://a/s> <p> <http://a/o> .', 1, 14, 'An IRI must be absolute'];
        yield 'relative object' => ['<http://a/s> <http://a/p> <o> .', 1, 27, 'An IRI must be absolute'];
        yield 'relative graph label' => [
            '<http://a/s> <http://a/p> <http://a/o> <g> .',
            1,
            40,
            'An IRI must be absolute',
        ];
        yield 'empty IRI' => ['<http://a/s> <http://a/p> <> .', 1, 27, 'An IRI must not be empty'];
        yield 'escape decoding to a forbidden character' => [
            '<http://a/s> <http://a/p> <http://a/\u0020> .',
            1,
            27,
            'contains a character that is not allowed in an IRI',
        ];
        yield 'IRI escape naming a surrogate' => [
            '<http://a/s> <http://a/p> <http://a/\uD800> .',
            1,
            27,
            'An escape in the IRI does not denote a Unicode scalar value',
        ];
        yield 'IRI escape above U+10FFFF' => [
            '<http://a/s> <http://a/p> <http://a/\U00110000> .',
            1,
            27,
            'An escape in the IRI does not denote a Unicode scalar value',
        ];
        yield 'blank node with a leading colon' => [
            '_::a <http://a/p> <http://a/o> .',
            1,
            1,
            'Malformed blank node label',
        ];
        yield 'blank node with an inner colon' => ['_:abc:def <http://a/p> <http://a/o> .', 1, 6, $predicate];
        yield 'blank node with a leading hyphen' => [
            '_:-a <http://a/p> <http://a/o> .',
            1,
            1,
            'Malformed blank node label',
        ];
        yield 'blank node with no label' => ['_: <http://a/p> <http://a/o> .', 1, 1, 'Malformed blank node label'];
        yield 'invalid UTF-8' => ["<http://a/s> <http://a/p> <http://a/\xff> .", 1, 1, 'The line is not valid UTF-8'];
        yield 'error on the second line' => [
            "<http://a/s> <http://a/p> <http://a/o> .\n<http://a/s> <http://a/p>",
            2,
            26,
            $object,
        ];
        yield 'CRLF counts as one line break' => [
            "<http://a/s> <http://a/p> <http://a/o> .\r\n<http://a/s> <http://a/p>",
            2,
            26,
            $object,
        ];
        yield 'blank lines are counted' => [
            "<http://a/s> <http://a/p> <http://a/o> .\n\n\n<http://a/s>",
            4,
            13,
            $predicate,
        ];
        yield 'comment lines are counted' => ["# one\n# two\n<http://a/s>", 3, 13, $predicate];
        yield 'column counts tabs as one' => ["\t\t<http://a/s>", 1, 15, $predicate];
    }

    public function testReportsTheOffendingLineAndTheCauseOfATermError(): void
    {
        try {
            (new Parser())->parse("<http://a/s> <http://a/p> <http://a/o> .\n<http://a/s> <http://a/p> <o> .");
        } catch (MalformedNQuads $exception) {
            $this->assertSame('<http://a/s> <http://a/p> <o> .', $exception->offendingLine);
            $this->assertInstanceOf(InvalidArgument::class, $exception->getPrevious());
            $this->assertSame('An IRI must be absolute; "o" has no scheme', $exception->getPrevious()->getMessage());

            return;
        }

        $this->fail('Expected MalformedNQuads to be thrown');
    }
}
