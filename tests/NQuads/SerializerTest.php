<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\GraphName;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\NQuads\Serializer;
use SocialWeb\Rdf\Quad;
use SocialWeb\Rdf\Term;
use SocialWeb\Rdf\Vocabulary\Rdf;
use SocialWeb\Rdf\Vocabulary\Xsd;
use SocialWeb\Test\Rdf\TestCase;

class SerializerTest extends TestCase
{
    public function testSerializesATripleInTheDefaultGraph(): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o'));

        $this->assertSame("<http://a/s> <http://a/p> <http://a/o> .\n", (new Serializer())->serializeQuad($quad));
    }

    public function testSerializesAQuadWithAnIriGraphLabel(): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o'), new Iri('http://a/g'));

        $this->assertSame(
            "<http://a/s> <http://a/p> <http://a/o> <http://a/g> .\n",
            (new Serializer())->serializeQuad($quad),
        );
    }

    public function testSerializesAQuadWithABlankNodeGraphLabel(): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o'), new BlankNode('g'));

        $this->assertSame("<http://a/s> <http://a/p> <http://a/o> _:g .\n", (new Serializer())->serializeQuad($quad));
    }

    public function testSerializesBlankNodes(): void
    {
        $quad = new Quad(new BlankNode('b0'), new Iri('http://a/p'), new BlankNode('b1'));

        $this->assertSame("_:b0 <http://a/p> _:b1 .\n", (new Serializer())->serializeQuad($quad));
    }

    public function testOmitsTheDatatypeOfXsdStringLiterals(): void
    {
        $serializer = new Serializer();
        $implicit = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('x'));
        $explicit = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('x', new Iri(Xsd::STRING)));

        $this->assertSame("<http://a/s> <http://a/p> \"x\" .\n", $serializer->serializeQuad($implicit));
        $this->assertSame("<http://a/s> <http://a/p> \"x\" .\n", $serializer->serializeQuad($explicit));
    }

    public function testWritesLanguageTagsAsStored(): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('x', language: 'en-US'));

        $this->assertSame("<http://a/s> <http://a/p> \"x\"@en-us .\n", (new Serializer())->serializeQuad($quad));
    }

    public function testWritesRdfLangStringOnlyAsALanguageTag(): void
    {
        $literal = new Literal('x', new Iri(Rdf::LANG_STRING), 'en');
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), $literal);

        $this->assertSame("<http://a/s> <http://a/p> \"x\"@en .\n", (new Serializer())->serializeQuad($quad));
    }

    public function testWritesOtherDatatypes(): void
    {
        $literal = new Literal('1', new Iri('http://www.w3.org/2001/XMLSchema#integer'));
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), $literal);

        $this->assertSame(
            "<http://a/s> <http://a/p> \"1\"^^<http://www.w3.org/2001/XMLSchema#integer> .\n",
            (new Serializer())->serializeQuad($quad),
        );
    }

    public function testWritesAnEmptyLiteral(): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal(''));

        $this->assertSame("<http://a/s> <http://a/p> \"\" .\n", (new Serializer())->serializeQuad($quad));
    }

    #[DataProvider('escapes')]
    public function testEscapesLiteralsPerTheCanonicalTable(string $lexicalForm, string $expected): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal($lexicalForm));

        $this->assertSame(
            '<http://a/s> <http://a/p> "' . $expected . "\" .\n",
            (new Serializer())->serializeQuad($quad),
        );
    }

    /**
     * Every code point in the table from RDFC-1.0 Appendix A, plus code
     * points on either side of each escaped range that must stay native
     *
     * @return iterable<string, array{string, string}>
     */
    public static function escapes(): iterable
    {
        yield 'U+0000 NUL' => ["\x00", '\u0000'];
        yield 'U+0001' => ["\x01", '\u0001'];
        yield 'U+0002' => ["\x02", '\u0002'];
        yield 'U+0003' => ["\x03", '\u0003'];
        yield 'U+0004' => ["\x04", '\u0004'];
        yield 'U+0005' => ["\x05", '\u0005'];
        yield 'U+0006' => ["\x06", '\u0006'];
        yield 'U+0007' => ["\x07", '\u0007'];
        yield 'U+0008 backspace' => ["\x08", '\b'];
        yield 'U+0009 tab' => ["\x09", '\t'];
        yield 'U+000A line feed' => ["\x0A", '\n'];
        yield 'U+000B vertical tab' => ["\x0B", '\u000B'];
        yield 'U+000C form feed' => ["\x0C", '\f'];
        yield 'U+000D carriage return' => ["\x0D", '\r'];
        yield 'U+000E' => ["\x0E", '\u000E'];
        yield 'U+000F' => ["\x0F", '\u000F'];
        yield 'U+0010' => ["\x10", '\u0010'];
        yield 'U+0011' => ["\x11", '\u0011'];
        yield 'U+0012' => ["\x12", '\u0012'];
        yield 'U+0013' => ["\x13", '\u0013'];
        yield 'U+0014' => ["\x14", '\u0014'];
        yield 'U+0015' => ["\x15", '\u0015'];
        yield 'U+0016' => ["\x16", '\u0016'];
        yield 'U+0017' => ["\x17", '\u0017'];
        yield 'U+0018' => ["\x18", '\u0018'];
        yield 'U+0019' => ["\x19", '\u0019'];
        yield 'U+001A' => ["\x1A", '\u001A'];
        yield 'U+001B' => ["\x1B", '\u001B'];
        yield 'U+001C' => ["\x1C", '\u001C'];
        yield 'U+001D' => ["\x1D", '\u001D'];
        yield 'U+001E' => ["\x1E", '\u001E'];
        yield 'U+001F unit separator' => ["\x1F", '\u001F'];
        yield 'U+0022 quotation mark' => ['"', '\"'];
        yield 'U+005C backslash' => ['\\', '\\\\'];
        yield 'U+007F delete' => ["\x7F", '\u007F'];
        yield 'U+FFFE' => ["\u{FFFE}", '\uFFFE'];
        yield 'U+FFFF' => ["\u{FFFF}", '\uFFFF'];
        yield 'U+0027 apostrophe, written natively' => ["'", "'"];
        yield 'U+0020 space, written natively' => [' ', ' '];
        yield 'U+007E tilde, written natively' => ['~', '~'];
        yield 'U+0080 first C1 control, written natively' => ["\u{80}", "\u{80}"];
        yield 'U+009F last C1 control, written natively' => ["\u{9F}", "\u{9F}"];
        yield 'U+00E9, written natively' => ['é', 'é'];
        yield 'U+20AC, written natively' => ['€', '€'];
        yield 'U+D7FF, written natively' => ["\u{D7FF}", "\u{D7FF}"];
        yield 'U+E000, written natively' => ["\u{E000}", "\u{E000}"];
        yield 'U+FFFD replacement character, written natively' => ["\u{FFFD}", "\u{FFFD}"];
        yield 'U+10000, written natively' => ["\u{10000}", "\u{10000}"];
        yield 'U+1F600, written natively' => ['😀', '😀'];
        yield 'U+10FFFF, written natively' => ["\u{10FFFF}", "\u{10FFFF}"];
    }

    public function testEscapesEveryOccurrenceInALexicalForm(): void
    {
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal("a\tb\tc\"d\\e\nf\x00g"));

        $this->assertSame(
            '<http://a/s> <http://a/p> "a\tb\tc\"d\\\\e\nf\u0000g" .' . "\n",
            (new Serializer())->serializeQuad($quad),
        );
    }

    public function testWritesIrisWithoutEscaping(): void
    {
        $quad = new Quad(
            new Iri('http://a/é'),
            new Iri('http://a/p?q=1&r=2#f'),
            new Literal('x', new Iri('http://a/€')),
        );

        $this->assertSame(
            "<http://a/é> <http://a/p?q=1&r=2#f> \"x\"^^<http://a/€> .\n",
            (new Serializer())->serializeQuad($quad),
        );
    }

    public function testSerializesADatasetInInsertionOrderWithOneLineFeedPerQuad(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o2')),
            new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o1')),
            new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('o'), new BlankNode('g')),
        ]);

        $this->assertSame(
            "<http://a/s> <http://a/p> <http://a/o2> .\n"
            . "<http://a/s> <http://a/p> <http://a/o1> .\n"
            . "<http://a/s> <http://a/p> \"o\" _:g .\n",
            (new Serializer())->serialize($dataset),
        );
    }

    public function testSerializesAnEmptyDatasetAsAnEmptyString(): void
    {
        $this->assertSame('', (new Serializer())->serialize(new Dataset()));
    }

    public function testRejectsAForeignTermImplementation(): void
    {
        $foreign = new class implements Term {
            public function equals(Term $other): bool
            {
                return false;
            }
        };
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), $foreign);

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Unsupported term type');

        (new Serializer())->serializeQuad($quad);
    }

    public function testRejectsAForeignGraphNameImplementation(): void
    {
        $foreign = new class implements GraphName {
            public function equals(GraphName $other): bool
            {
                return false;
            }
        };
        $quad = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Iri('http://a/o'), $foreign);

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Unsupported term type');

        (new Serializer())->serializeQuad($quad);
    }
}
