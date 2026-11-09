<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\DefaultGraph;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Exception\MalformedNQuads;
use SocialWeb\Rdf\Exception\MalformedNQuadsReason;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Rdf\Quad;
use SocialWeb\Rdf\Vocabulary\Rdf;
use SocialWeb\Rdf\Vocabulary\Xsd;
use SocialWeb\Test\Rdf\TestCase;

use function iterator_to_array;
use function str_repeat;

final class ParserTest extends TestCase
{
    #[DataProvider('documentsWithoutStatements')]
    public function testParsesADocumentWithoutStatementsIntoNoQuads(string $document): void
    {
        $this->assertSame([], iterator_to_array(Parser::parse($document), false));
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
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> <http://a/o> .'), false);

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
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> <http://a/o> <http://a/g> .'), false);

        $this->assertTrue(new Quad(
            new Iri('http://a/s'),
            new Iri('http://a/p'),
            new Iri('http://a/o'),
            new Iri('http://a/g'),
        )->equals($quads[0]));
    }

    public function testParsesAQuadWithABlankNodeGraphLabel(): void
    {
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> <http://a/o> _:g .'), false);

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
        $quads = iterator_to_array(Parser::parse("_:$label <http://a/p> _:$label ."), false);

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

    public function testParsesASimpleLiteralAsXsdString(): void
    {
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "Alice" .'), false);

        $this->assertTrue(new Literal('Alice')->equals($quads[0]->object));
        $this->assertInstanceOf(Literal::class, $quads[0]->object);
        $this->assertSame(Xsd::STRING, $quads[0]->object->datatype->value);
    }

    public function testParsesALanguageTaggedLiteral(): void
    {
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "Alice"@en-US .'), false);

        $this->assertInstanceOf(Literal::class, $quads[0]->object);
        $this->assertSame('Alice', $quads[0]->object->lexicalForm);
        $this->assertSame('en-us', $quads[0]->object->language);
        $this->assertSame(Rdf::LANG_STRING, $quads[0]->object->datatype->value);
    }

    public function testParsesADatatypedLiteral(): void
    {
        $quads = iterator_to_array(Parser::parse(
            '<http://a/s> <http://a/p> "42"^^<http://www.w3.org/2001/XMLSchema#integer> .',
        ), false);

        $this->assertTrue(new Literal('42', new Iri('http://www.w3.org/2001/XMLSchema#integer'))
            ->equals($quads[0]->object));
    }

    public function testParsesAnExplicitXsdStringDatatype(): void
    {
        $quads = iterator_to_array(Parser::parse(
            '<http://a/s> <http://a/p> "42"^^<http://www.w3.org/2001/XMLSchema#string> .',
        ), false);

        $this->assertTrue(new Literal('42')->equals($quads[0]->object));
    }

    public function testParsesAnEmptyLiteral(): void
    {
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "" .'), false);

        $this->assertTrue(new Literal('')->equals($quads[0]->object));
    }

    #[DataProvider('escapedLiterals')]
    public function testDecodesEscapesInLiterals(string $encoded, string $decoded): void
    {
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "' . $encoded . '" .'), false);

        $this->assertInstanceOf(Literal::class, $quads[0]->object);
        $this->assertSame($decoded, $quads[0]->object->lexicalForm);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function escapedLiterals(): iterable
    {
        yield 'tab' => ['a\tb', "a\tb"];
        yield 'backspace' => ['a\bb', "a\x08b"];
        yield 'line feed' => ['a\nb', "a\nb"];
        yield 'carriage return' => ['a\rb', "a\rb"];
        yield 'form feed' => ['a\fb', "a\x0Cb"];
        yield 'quotation mark' => ['a\"b', 'a"b'];
        yield 'apostrophe' => ["a\\'b", "a'b"];
        yield 'backslash' => ['a\\\\b', 'a\\b'];
        yield 'backslash before quotation mark' => ['a\\\\\"b', 'a\\"b'];
        yield 'all ECHARs' => ['\t\b\n\r\f\"\'\\\\', "\t\x08\n\r\x0C\"'\\"];
        yield 'short escape, lowercase hex' => ['\u00e9', 'é'];
        yield 'short escape, uppercase hex' => ['\u00E9', 'é'];
        yield 'long escape' => ['\U0001F600', '😀'];
        yield 'long escape of a BMP code point' => ['\U000000E9', 'é'];
        yield 'escaped NUL' => ['\u0000', "\x00"];
        yield 'escaped DEL' => ['\u007F', "\x7F"];
        yield 'escape adjacent to text' => ['a\u00E9b', 'aéb'];
        yield 'two escapes in a row' => ['\u00E9\u20AC', 'é€'];
        yield 'escape at the end' => ['a\u00E9', 'aé'];
        yield 'native non-ASCII' => ['é€😀', 'é€😀'];
        yield 'text with no escapes' => ['plain', 'plain'];
    }

    #[DataProvider('utf8Boundaries')]
    public function testEncodesEveryUtf8SequenceLengthCorrectly(string $encoded, string $decoded): void
    {
        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "' . $encoded . '" .'), false);

        $this->assertInstanceOf(Literal::class, $quads[0]->object);
        $this->assertSame($decoded, $quads[0]->object->lexicalForm);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utf8Boundaries(): iterable
    {
        yield 'U+0001' => ['\u0001', "\u{1}"];
        yield 'U+007F, last one-byte sequence' => ['\u007F', "\u{7F}"];
        yield 'U+0080, first two-byte sequence' => ['\u0080', "\u{80}"];
        yield 'U+07FF, last two-byte sequence' => ['\u07FF', "\u{7FF}"];
        yield 'U+0800, first three-byte sequence' => ['\u0800', "\u{800}"];
        yield 'U+D7FF, last code point before the surrogates' => ['\uD7FF', "\u{D7FF}"];
        yield 'U+E000, first code point after the surrogates' => ['\uE000', "\u{E000}"];
        yield 'U+FFFD' => ['\uFFFD', "\u{FFFD}"];
        yield 'U+FFFF, last three-byte sequence' => ['\uFFFF', "\u{FFFF}"];
        yield 'U+10000, first four-byte sequence' => ['\U00010000', "\u{10000}"];
        yield 'U+10FFFF, last code point' => ['\U0010FFFF', "\u{10FFFF}"];
    }

    public function testDecodesEscapesInIris(): void
    {
        $quads = iterator_to_array(Parser::parse(
            '<http://a/\u00E9> <http://a/\U0001F600> <http://a/\u00E9\u20AC> .',
        ), false);

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

        $this->assertCount(3, iterator_to_array(Parser::parse($document), false));
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
        $this->assertCount(1, iterator_to_array(Parser::parse('<http://a/s> <http://a/p> <http://a/o> .'), false));
    }

    public function testAcceptsMixedLineEndingsInOneDocument(): void
    {
        $document = "<http://a/s> <http://a/p> <http://a/o1> .\r\n"
            . "<http://a/s> <http://a/p> <http://a/o2> .\r"
            . "<http://a/s> <http://a/p> <http://a/o3> .\n"
            . "\r\n"
            . "<http://a/s> <http://a/p> <http://a/o4> .\r";

        $objects = [];

        foreach (Parser::parse($document) as $quad) {
            $this->assertInstanceOf(Iri::class, $quad->object);
            $objects[] = $quad->object->value;
        }

        $this->assertSame(['http://a/o1', 'http://a/o2', 'http://a/o3', 'http://a/o4'], $objects);
    }

    #[DataProvider('whitespaceVariations')]
    public function testAcceptsWhitespaceAndCommentsAroundTerms(string $document): void
    {
        $quads = iterator_to_array(Parser::parse($document), false);

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
        $quads = iterator_to_array(Parser::parse('_:s <http://a/p> _:o.'), false);

        $this->assertTrue(new Quad(new BlankNode('s'), new Iri('http://a/p'), new BlankNode('o'))
            ->equals($quads[0]));
    }

    public function testYieldsEveryStatementInOrder(): void
    {
        $document = "<http://a/s> <http://a/p> <http://a/o2> .\n"
            . "<http://a/s> <http://a/p> <http://a/o1> .\n"
            . "<http://a/s> <http://a/p> <http://a/o2> .\n";

        $quads = iterator_to_array(Parser::parse($document), false);

        $this->assertCount(3, $quads);
        $this->assertTrue($quads[0]->equals($quads[2]));
        $this->assertFalse($quads[0]->equals($quads[1]));
    }

    public function testYieldsStatementsBeforeReachingALaterError(): void
    {
        $quads = Parser::parse("<http://a/s> <http://a/p> <http://a/o> .\nnot a statement\n");

        $this->assertInstanceOf(Generator::class, $quads);
        $first = $quads->current();
        $this->assertInstanceOf(Quad::class, $first);
        $expected = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o'));
        $this->assertTrue($expected->equals($first));

        $this->expectException(MalformedNQuads::class);

        $quads->next();
    }

    public function testParsesALiteralOfOneHundredThousandCharacters(): void
    {
        $lexicalForm = str_repeat('a', 100000);

        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "' . $lexicalForm . '" .'), false);

        $this->assertInstanceOf(Literal::class, $quads[0]->object);
        $this->assertSame($lexicalForm, $quads[0]->object->lexicalForm);
    }

    public function testParsesAnIriOfOneHundredThousandCharacters(): void
    {
        $value = 'http://a/' . str_repeat('x', 100000);

        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> <' . $value . '> .'), false);

        $this->assertInstanceOf(Iri::class, $quads[0]->object);
        $this->assertSame($value, $quads[0]->object->value);
    }

    public function testParsesALiteralOfTensOfThousandsOfEscapes(): void
    {
        $encoded = str_repeat('\"', 20000) . str_repeat('\u00E9', 20000);
        $decoded = str_repeat('"', 20000) . str_repeat('é', 20000);

        $quads = iterator_to_array(Parser::parse('<http://a/s> <http://a/p> "' . $encoded . '" .'), false);

        $this->assertInstanceOf(Literal::class, $quads[0]->object);
        $this->assertSame($decoded, $quads[0]->object->lexicalForm);
    }

    public function testRejectsAnUnterminatedLiteralOfOneHundredThousandCharacters(): void
    {
        $document = '<http://a/s> <http://a/p> "' . str_repeat('a', 100000) . ' .';

        try {
            iterator_to_array(Parser::parse($document), false);
        } catch (MalformedNQuads $exception) {
            $this->assertSame(1, $exception->lineNumber);
            $this->assertSame(27, $exception->columnNumber);
            $this->assertStringContainsString('malformed string literal', $exception->getMessage());
            $this->assertSame(MalformedNQuadsReason::MalformedStringLiteral, $exception->reason);

            return;
        }

        $this->fail('Expected MalformedNQuads to be thrown');
    }

    #[DataProvider('malformedDocuments')]
    public function testRejectsMalformedDocuments(
        string $document,
        int $lineNumber,
        int $columnNumber,
        string $detail,
        MalformedNQuadsReason $reason,
    ): void {
        try {
            iterator_to_array(Parser::parse($document), false);
        } catch (MalformedNQuads $exception) {
            $this->assertSame($lineNumber, $exception->lineNumber, 'line number');
            $this->assertSame($columnNumber, $exception->columnNumber, 'column number');
            $this->assertStringContainsString($detail, $exception->getMessage());
            $this->assertSame($reason, $exception->reason, 'reason');

            return;
        }

        $this->fail('Expected MalformedNQuads to be thrown');
    }

    /**
     * @return iterable<string, array{string, int, int, string, MalformedNQuadsReason}>
     */
    public static function malformedDocuments(): iterable
    {
        $subject = ['expected an IRI or a blank node label as the subject', MalformedNQuadsReason::InvalidSubject];
        $predicate = ['expected an IRI as the predicate', MalformedNQuadsReason::InvalidPredicate];
        $object = [
            'expected an IRI, a blank node label, or a literal as the object',
            MalformedNQuadsReason::InvalidObject,
        ];
        $terminator = ['expected "." to end the statement', MalformedNQuadsReason::MissingTerminator];
        $endOfLine = ['expected the end of the line after "."', MalformedNQuadsReason::TextAfterTerminator];
        $iriReference = ['malformed IRI reference', MalformedNQuadsReason::MalformedIriReference];
        $relativeIri = ['an IRI must be absolute', MalformedNQuadsReason::InvalidIri];
        $iriEscape = [
            'an escape in the IRI does not denote a Unicode scalar value',
            MalformedNQuadsReason::InvalidIriEscape,
        ];
        $blankNodeLabel = ['malformed blank node label', MalformedNQuadsReason::MalformedBlankNodeLabel];
        $stringLiteral = ['malformed string literal', MalformedNQuadsReason::MalformedStringLiteral];
        $literalEscape = [
            'an escape in the literal does not denote a Unicode scalar value',
            MalformedNQuadsReason::InvalidLiteralEscape,
        ];
        $languageTag = ['malformed language tag', MalformedNQuadsReason::MalformedLanguageTag];

        yield 'Turtle prefix directive' => ['@prefix : <http://a/> .', 1, 1, ...$subject];
        yield 'Turtle base directive' => ['@base <http://a/> .', 1, 1, ...$subject];
        yield 'literal subject' => ['"s" <http://a/p> <http://a/o> .', 1, 1, ...$subject];
        yield 'bare word subject' => ['s <http://a/p> <http://a/o> .', 1, 1, ...$subject];
        yield 'only a subject' => ['<http://a/s>', 1, 13, ...$predicate];
        yield 'literal predicate' => ['<http://a/s> "p" <http://a/o> .', 1, 14, ...$predicate];
        yield 'blank node predicate' => ['<http://a/s> _:p <http://a/o> .', 1, 14, ...$predicate];
        yield 'number object' => ['<http://a/s> <http://a/p> 1 .', 1, 27, ...$object];
        yield 'decimal object' => ['<http://a/s> <http://a/p> 1.0 .', 1, 27, ...$object];
        yield 'single-quoted object' => ["<http://a/s> <http://a/p> 'o' .", 1, 27, ...$object];
        yield 'long single-quoted object' => ["<http://a/s> <http://a/p> '''o''' .", 1, 27, ...$object];
        yield 'missing object' => ['<http://a/s> <http://a/p> .', 1, 27, ...$object];
        yield 'missing terminator' => ['<http://a/s> <http://a/p> <http://a/o>', 1, 39, ...$terminator];
        yield 'missing terminator after a graph label' => [
            '<http://a/s> <http://a/p> <http://a/o> _:g',
            1,
            43,
            ...$terminator,
        ];
        yield 'object list' => ['<http://a/s> <http://a/p> <http://a/o>, <http://a/o2> .', 1, 39, ...$terminator];
        yield 'predicate list' => [
            '<http://a/s> <http://a/p> <http://a/o>; <http://a/p2> <http://a/o2> .',
            1,
            39,
            ...$terminator,
        ];
        yield 'fifth term' => [
            '<http://a/s> <http://a/p> <http://a/o> <http://a/g> <http://a/n> .',
            1,
            53,
            ...$terminator,
        ];
        yield 'text after the terminator' => [
            '<http://a/s> <http://a/p> <http://a/o> . <http://a/s>',
            1,
            42,
            ...$endOfLine,
        ];
        yield 'two statements on one line' => [
            '<http://a/s> <http://a/p> <http://a/o> . <http://a/s> <http://a/p> <http://a/o> .',
            1,
            42,
            ...$endOfLine,
        ];
        yield 'two terminators' => ['<http://a/s> <http://a/p> <http://a/o> ..', 1, 41, ...$endOfLine];
        yield 'IRI with a space' => ['<http://a/ s> <http://a/p> <http://a/o> .', 1, 1, ...$iriReference];
        yield 'IRI with a bad short escape' => [
            '<http://a/s> <http://a/p> <http://a/\u00ZZ> .',
            1,
            27,
            ...$iriReference,
        ];
        yield 'IRI with a bad long escape' => [
            '<http://a/s> <http://a/p> <http://a/\U00ZZ1111> .',
            1,
            27,
            ...$iriReference,
        ];
        yield 'IRI with an ECHAR' => ['<http://a/s> <http://a/p> <http://a/\n> .', 1, 27, ...$iriReference];
        yield 'IRI with an escaped slash' => [
            '<http://a/s> <http://a/p> <http://a/\/> .',
            1,
            27,
            ...$iriReference,
        ];
        yield 'unterminated IRI' => ['<http://a/s> <http://a/p> <http://a/o .', 1, 27, ...$iriReference];
        yield 'relative subject' => [
            '<s> <http://a/p> <http://a/o> .',
            1,
            1,
            'an IRI must be absolute; "s" has no scheme',
            MalformedNQuadsReason::InvalidIri,
        ];
        yield 'relative predicate' => ['<http://a/s> <p> <http://a/o> .', 1, 14, ...$relativeIri];
        yield 'relative object' => ['<http://a/s> <http://a/p> <o> .', 1, 27, ...$relativeIri];
        yield 'relative graph label' => [
            '<http://a/s> <http://a/p> <http://a/o> <g> .',
            1,
            40,
            ...$relativeIri,
        ];
        yield 'empty IRI' => [
            '<http://a/s> <http://a/p> <> .',
            1,
            27,
            'an IRI must not be empty',
            MalformedNQuadsReason::InvalidIri,
        ];
        yield 'escape decoding to a forbidden character' => [
            '<http://a/s> <http://a/p> <http://a/\u0020> .',
            1,
            27,
            'contains a character that is not allowed in an IRI',
            MalformedNQuadsReason::InvalidIri,
        ];
        yield 'IRI escape naming a surrogate' => [
            '<http://a/s> <http://a/p> <http://a/\uD800> .',
            1,
            27,
            ...$iriEscape,
        ];
        yield 'IRI escape above U+10FFFF' => [
            '<http://a/s> <http://a/p> <http://a/\U00110000> .',
            1,
            27,
            ...$iriEscape,
        ];
        yield 'blank node with a leading colon' => [
            '_::a <http://a/p> <http://a/o> .',
            1,
            1,
            ...$blankNodeLabel,
        ];
        yield 'blank node with an inner colon' => ['_:abc:def <http://a/p> <http://a/o> .', 1, 6, ...$predicate];
        yield 'blank node with a leading hyphen' => [
            '_:-a <http://a/p> <http://a/o> .',
            1,
            1,
            ...$blankNodeLabel,
        ];
        yield 'blank node with no label' => ['_: <http://a/p> <http://a/o> .', 1, 1, ...$blankNodeLabel];
        yield 'invalid UTF-8' => [
            "<http://a/s> <http://a/p> <http://a/\xff> .",
            1,
            1,
            'the line is not valid UTF-8',
            MalformedNQuadsReason::InvalidUtf8,
        ];
        yield 'error on the second line' => [
            "<http://a/s> <http://a/p> <http://a/o> .\n<http://a/s> <http://a/p>",
            2,
            26,
            ...$object,
        ];
        yield 'CRLF counts as one line break' => [
            "<http://a/s> <http://a/p> <http://a/o> .\r\n<http://a/s> <http://a/p>",
            2,
            26,
            ...$object,
        ];
        yield 'blank lines are counted' => [
            "<http://a/s> <http://a/p> <http://a/o> .\n\n\n<http://a/s>",
            4,
            13,
            ...$predicate,
        ];
        yield 'comment lines are counted' => ["# one\n# two\n<http://a/s>", 3, 13, ...$predicate];
        yield 'column counts tabs as one' => ["\t\t<http://a/s>", 1, 15, ...$predicate];
        yield 'long double-quoted object' => ['<http://a/s> <http://a/p> """o""" .', 1, 29, ...$terminator];
        yield 'simple literal graph label' => ['<http://a/s> <http://a/p> <http://a/o> "g" .', 1, 40, ...$terminator];
        yield 'language-tagged literal graph label' => [
            '<http://a/s> <http://a/p> <http://a/o> "g"@en .',
            1,
            40,
            ...$terminator,
        ];
        yield 'relative datatype' => ['<http://a/s> <http://a/p> "o"^^<dt> .', 1, 32, ...$relativeIri];
        yield 'literal escape naming a high surrogate' => [
            '<http://a/s> <http://a/p> "\uD800" .',
            1,
            27,
            ...$literalEscape,
        ];
        yield 'literal escape naming a low surrogate' => [
            '<http://a/s> <http://a/p> "\uDFFF" .',
            1,
            27,
            ...$literalEscape,
        ];
        yield 'literal escape above U+10FFFF' => [
            '<http://a/s> <http://a/p> "\U00110000" .',
            1,
            27,
            ...$literalEscape,
        ];
        yield 'literal escape far above U+10FFFF' => [
            '<http://a/s> <http://a/p> "\UFFFFFFFF" .',
            1,
            27,
            ...$literalEscape,
        ];
        yield 'unterminated literal' => ['<http://a/s> <http://a/p> "o .', 1, 27, ...$stringLiteral];
        yield 'mismatched quotes' => ["<http://a/s> <http://a/p> \"o' .", 1, 27, ...$stringLiteral];
        yield 'bad ECHAR' => ['<http://a/s> <http://a/p> "a\zb" .', 1, 27, ...$stringLiteral];
        yield 'bad short UCHAR' => ['<http://a/s> <http://a/p> "\uWXYZ" .', 1, 27, ...$stringLiteral];
        yield 'bad long UCHAR' => ['<http://a/s> <http://a/p> "\U0000WXYZ" .', 1, 27, ...$stringLiteral];
        yield 'bad language tag' => ['<http://a/s> <http://a/p> "o"@1 .', 1, 30, ...$languageTag];
        yield 'empty language tag' => ['<http://a/s> <http://a/p> "o"@ .', 1, 30, ...$languageTag];
        yield 'missing datatype' => [
            '<http://a/s> <http://a/p> "o"^^ .',
            1,
            32,
            'expected a datatype IRI after "^^"',
            MalformedNQuadsReason::MissingDatatype,
        ];
        yield 'single caret' => ['<http://a/s> <http://a/p> "o"^<http://a/dt> .', 1, 30, ...$terminator];
        yield 'both a language tag and a datatype' => [
            '<http://a/s> <http://a/p> "o"@en^^<http://a/dt> .',
            1,
            33,
            ...$terminator,
        ];
        yield 'langString without a language tag' => [
            '<http://a/s> <http://a/p> "o"^^<http://www.w3.org/1999/02/22-rdf-syntax-ns#langString> .',
            1,
            27,
            'a literal with the datatype rdf:langString must have a language tag',
            MalformedNQuadsReason::InvalidLiteral,
        ];
        yield 'column counts code points' => ['<http://a/s> <http://a/p> "é€😀" x .', 1, 33, ...$terminator];
        yield 'mixed line endings each count as one line break' => [
            "<http://a/s> <http://a/p> <http://a/o1> .\r\n<http://a/s> <http://a/p> <http://a/o2> .\r"
            . "<http://a/s> <http://a/p> <http://a/o3> .\n\r\n<http://a/s> <http://a/p> <http://a/o4> .\rx",
            6,
            1,
            ...$subject,
        ];
    }

    public function testReportsTheOffendingLineAndTheCauseOfATermError(): void
    {
        try {
            iterator_to_array(
                Parser::parse("<http://a/s> <http://a/p> <http://a/o> .\n<http://a/s> <http://a/p> <o> ."),
                false,
            );
        } catch (MalformedNQuads $exception) {
            $this->assertSame('<http://a/s> <http://a/p> <o> .', $exception->offendingLine);
            $this->assertInstanceOf(InvalidArgument::class, $exception->getPrevious());
            $this->assertSame('An IRI must be absolute; "o" has no scheme', $exception->getPrevious()->getMessage());

            return;
        }

        $this->fail('Expected MalformedNQuads to be thrown');
    }
}
