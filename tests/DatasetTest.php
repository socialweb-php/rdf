<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use ArrayIterator;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;
use SocialWeb\Rdf\Resource;
use SocialWeb\Rdf\Term;

use function iterator_to_array;

class DatasetTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $dataset = new Dataset();

        $this->assertCount(0, $dataset);
        $this->assertSame([], $dataset->toArray());
        $this->assertSame([], iterator_to_array($dataset));
    }

    public function testAddsQuadsInInsertionOrder(): void
    {
        $first = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1'));
        $second = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('2'));
        $third = new Quad(new BlankNode('b0'), new Iri('a:p'), new Iri('a:o'), new Iri('a:g'));

        $dataset = new Dataset();
        $dataset->add($third);
        $dataset->add($first);
        $dataset->add($second);

        $this->assertCount(3, $dataset);
        $this->assertSame([$third, $first, $second], $dataset->toArray());
        $this->assertSame([$third, $first, $second], iterator_to_array($dataset));
    }

    public function testConstructorAcceptsAnIterableOfQuads(): void
    {
        $first = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1'));
        $second = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('2'));

        $dataset = new Dataset(new ArrayIterator([$first, $second]));

        $this->assertSame([$first, $second], $dataset->toArray());
    }

    public function testAddingAnEqualQuadIsANoOp(): void
    {
        $original = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o', language: 'en'), new Iri('a:g'));
        $duplicate = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o', language: 'EN'), new Iri('a:g'));

        $dataset = new Dataset([$original]);
        $dataset->add($duplicate);

        $this->assertCount(1, $dataset);
        $this->assertSame($original, $dataset->toArray()[0]);
    }

    public function testContainsUsesQuadEquality(): void
    {
        $dataset = new Dataset([new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o'))]);

        $this->assertTrue($dataset->contains(new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o'))));
        $this->assertFalse($dataset->contains(new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:o'))));
        $this->assertFalse(
            $dataset->contains(new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o'), new Iri('a:g'))),
        );
    }

    public function testRemovesByEquality(): void
    {
        $keep = new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1'));
        $dataset = new Dataset([$keep, new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('2'))]);

        $dataset->remove(new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('2')));
        $dataset->remove(new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('missing')));

        $this->assertSame([$keep], $dataset->toArray());
    }

    public function testDistinguishesQuadsWhoseComponentsConcatenateIdentically(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('ab')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('a b')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('a'), new Iri('b:x')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Iri('a:o')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('a:o')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new BlankNode('a:o')),
            new Quad(new BlankNode('s'), new Iri('a:p'), new Literal('o')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o'), new BlankNode('g')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('o'), new Iri('a:g')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('', new Iri('a:a:'))),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal(':a', new Iri('a:'))),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1', new Iri('a:abcdefg3b:x'))),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('13a:abcdefg', new Iri('b:x'))),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1', new Iri('a:cdefgh:3b:x'))),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal(':13a:cdefgh', new Iri('b:x'))),
        ]);

        $this->assertCount(15, $dataset);
    }

    public function testDistinguishesQuadsByPredicate(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('a:s'), new Iri('a:p1'), new Iri('a:o')),
            new Quad(new Iri('a:s'), new Iri('a:p2'), new Iri('a:o')),
        ]);

        $this->assertCount(2, $dataset);
    }

    public function testDistinguishesQuadsByComponentValueWithinTheSameType(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('a:s1'), new Iri('a:p'), new Iri('a:o')),
            new Quad(new Iri('a:s2'), new Iri('a:p'), new Iri('a:o')),
            new Quad(new BlankNode('b1'), new Iri('a:p'), new Iri('a:o')),
            new Quad(new BlankNode('b2'), new Iri('a:p'), new Iri('a:o')),
        ]);

        $this->assertCount(4, $dataset);
    }

    public function testDistinguishesLiteralsByDatatypeAndLanguage(): void
    {
        $integer = new Iri('http://www.w3.org/2001/XMLSchema#integer');
        $dataset = new Dataset([
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1', $integer)),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1', language: 'en')),
            new Quad(new Iri('a:s'), new Iri('a:p'), new Literal('1', language: 'fr')),
        ]);

        $this->assertCount(4, $dataset);
    }

    public function testRejectsATermItDoesNotKnowHowToKey(): void
    {
        $foreign = new class implements Resource {
            public function equals(Term $other): bool
            {
                return false;
            }
        };

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Unsupported term type');

        (new Dataset())->add(new Quad($foreign, new Iri('a:p'), new Iri('a:o')));
    }
}
