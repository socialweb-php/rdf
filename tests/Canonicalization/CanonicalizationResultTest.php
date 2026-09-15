<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Canonicalization\CanonicalizationResult;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;
use SocialWeb\Test\Rdf\TestCase;

class CanonicalizationResultTest extends TestCase
{
    public function testExposesTheDatasetAndTheIssuedIdentifiers(): void
    {
        $dataset = new Dataset([new Quad(new BlankNode('c14n0'), new Iri('http://a/p'), new Literal('x'))]);
        $result = new CanonicalizationResult($dataset, ['e0' => 'c14n0']);

        $this->assertSame($dataset, $result->dataset());
        $this->assertSame(['e0' => 'c14n0'], $result->issuedIdentifiers());
    }

    public function testRendersTheQuadsInCodePointOrder(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('b')),
            new Quad(new BlankNode('c14n0'), new Iri('http://a/p'), new Literal('x')),
            new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('a')),
            new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('a'), new BlankNode('c14n0')),
        ]);

        $this->assertSame(
            "<http://a/s> <http://a/p> \"a\" .\n"
            . "<http://a/s> <http://a/p> \"a\" _:c14n0 .\n"
            . "<http://a/s> <http://a/p> \"b\" .\n"
            . "_:c14n0 <http://a/p> \"x\" .\n",
            (new CanonicalizationResult($dataset, []))->toNQuads(),
        );
    }

    public function testRendersAnEmptyDatasetAsAnEmptyString(): void
    {
        $this->assertSame('', (new CanonicalizationResult(new Dataset(), []))->toNQuads());
    }
}
