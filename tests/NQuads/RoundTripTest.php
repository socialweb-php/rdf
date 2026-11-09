<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;
use SocialWeb\Test\Rdf\TestCase;

use function count;
use function iterator_to_array;

/**
 * Parsing the serialization of a dataset yields an equal dataset, and
 * serializing that dataset yields the same text
 */
final class RoundTripTest extends TestCase
{
    public function testAHandBuiltDatasetSurvivesARoundTrip(): void
    {
        $subject = new Iri('http://example.com/s');
        $predicate = new Iri('http://example.com/p');
        $dataset = new Dataset([
            new Quad($subject, $predicate, new Iri('http://example.com/o')),
            new Quad($subject, $predicate, new BlankNode('b0')),
            new Quad(new BlankNode('b0'), $predicate, new Literal('plain')),
            new Quad($subject, $predicate, new Literal('tagged', language: 'en-US')),
            new Quad($subject, $predicate, new Literal('1', new Iri('http://www.w3.org/2001/XMLSchema#integer'))),
            new Quad($subject, $predicate, new Literal("controls \x00\x01\x08\t\n\x0B\x0C\r\x1F\x7F")),
            new Quad($subject, $predicate, new Literal('quote " backslash \\ apostrophe \'')),
            new Quad($subject, $predicate, new Literal("non-ASCII é € 😀 \u{FFFD} \u{FFFE} \u{FFFF} \u{10FFFF}")),
            new Quad($subject, $predicate, new Literal('')),
            new Quad($subject, $predicate, new Iri('http://example.com/o'), new Iri('http://example.com/g')),
            new Quad($subject, $predicate, new Iri('http://example.com/o'), new BlankNode('g')),
            new Quad(new Iri('http://example.com/é'), new Iri('http://example.com/p?q=1&r=2#f'), new Literal('x')),
        ]);

        $serialized = $dataset->toNQuads();
        $parsed = Dataset::fromNQuads($serialized);

        $this->assertDatasetEquals($dataset, $parsed);
        $this->assertSame($serialized, $parsed->toNQuads());
    }

    #[DataProvider('positiveSyntaxTests')]
    public function testEveryPositiveW3cFixtureSurvivesARoundTrip(string $path): void
    {
        $dataset = Dataset::fromNQuads(W3cManifest::read($path));

        $serialized = $dataset->toNQuads();
        $parsed = Dataset::fromNQuads($serialized);

        $this->assertDatasetEquals($dataset, $parsed);
        $this->assertSame($serialized, $parsed->toNQuads());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function positiveSyntaxTests(): iterable
    {
        return W3cManifest::entries(W3cManifest::POSITIVE);
    }

    private function assertDatasetEquals(Dataset $expected, Dataset $actual): void
    {
        $expectedQuads = iterator_to_array($expected);
        $actualQuads = iterator_to_array($actual);

        $this->assertCount(count($expectedQuads), $actualQuads);

        foreach ($expectedQuads as $index => $quad) {
            $this->assertTrue($quad->equals($actualQuads[$index]), "quad at index $index");
        }
    }
}
