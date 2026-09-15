<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Exception\IterationLimitExceeded;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Rdf\NQuads\Serializer;
use SocialWeb\Rdf\Quad;
use SocialWeb\Test\Rdf\TestCase;

class CanonicalizerTest extends TestCase
{
    /**
     * Two blank nodes with the same first degree hash, which only N-degree
     * hashing can tell apart; it takes four calls, so a work factor of 1
     * (a limit of 2) is not enough
     */
    private const string CIRCLE = "_:a <http://a/p> _:b .\n_:b <http://a/p> _:a .\n";

    private const string CIRCLE_CANONICAL = "_:c14n0 <http://a/p> _:c14n1 .\n_:c14n1 <http://a/p> _:c14n0 .\n";

    /**
     * W3C test020, whose canonical labels differ between SHA-256 and SHA-384
     */
    private const string DIAMOND = "<http://example.org/vocab#test> <http://example.org/vocab#A> _:e0 .\n"
        . "<http://example.org/vocab#test> <http://example.org/vocab#B> _:e1 .\n"
        . "_:e0 <http://example.org/vocab#next> _:e2 .\n"
        . "_:e1 <http://example.org/vocab#next> _:e2 .\n";

    public function testRejectsAnUnknownHashAlgorithm(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('"nope" is not a hash algorithm this PHP supports');

        new Canonicalizer('nope');
    }

    public function testRejectsANegativeWorkFactor(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The work factor must not be negative; -1 given');

        new Canonicalizer('sha256', -1);
    }

    public function testRejectsANegativeDeepIterationLimit(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('The deep iteration limit must not be negative; -5 given');

        new Canonicalizer('sha256', 1, -5);
    }

    public function testCanonicalizesADatasetWithoutBlankNodes(): void
    {
        $first = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('b'));
        $second = new Quad(new Iri('http://a/s'), new Iri('http://a/p'), new Literal('a'));

        $result = (new Canonicalizer())->canonicalize(new Dataset([$first, $second]));

        $this->assertSame(
            "<http://a/s> <http://a/p> \"a\" .\n<http://a/s> <http://a/p> \"b\" .\n",
            $result->toNQuads(),
        );
        $this->assertSame([], $result->issuedIdentifiers());
        $this->assertCount(2, $result->dataset());
        $this->assertTrue($result->dataset()->toArray()[0]->equals($first));
        $this->assertTrue($result->dataset()->toArray()[1]->equals($second));
    }

    public function testIssuesCanonicalIdentifiersInFirstDegreeHashOrder(): void
    {
        $dataset = (new Parser())->parse("_:b <http://a/p> <http://a/o> .\n_:a <http://a/p> _:b .\n");

        $result = (new Canonicalizer())->canonicalize($dataset);

        $this->assertSame("_:c14n0 <http://a/p> _:c14n1 .\n_:c14n1 <http://a/p> <http://a/o> .\n", $result->toNQuads());
        $this->assertSame(['a' => 'c14n0', 'b' => 'c14n1'], $result->issuedIdentifiers());
        $this->assertSame(
            "_:c14n1 <http://a/p> <http://a/o> .\n_:c14n0 <http://a/p> _:c14n1 .\n",
            (new Serializer())->serialize($result->dataset()),
        );
    }

    public function testListsAQuadOnceForABlankNodeItMentionsTwice(): void
    {
        // Were the self-referencing quad hashed twice, x would sort after y.
        $dataset = (new Parser())->parse("_:x <http://a/p> _:x .\n_:y <http://a/p> <http://a/o> .\n");

        $result = (new Canonicalizer())->canonicalize($dataset);

        $this->assertSame("_:c14n0 <http://a/p> _:c14n0 .\n_:c14n1 <http://a/p> <http://a/o> .\n", $result->toNQuads());
    }

    public function testHandlesNumericBlankNodeLabels(): void
    {
        $dataset = (new Parser())->parse("_:0 <http://a/p> _:1 .\n_:1 <http://a/p> <http://a/o> .\n");

        $result = (new Canonicalizer())->canonicalize($dataset);

        $this->assertSame("_:c14n0 <http://a/p> _:c14n1 .\n_:c14n1 <http://a/p> <http://a/o> .\n", $result->toNQuads());
        $this->assertSame([0 => 'c14n0', 1 => 'c14n1'], $result->issuedIdentifiers());
    }

    public function testResolvesSharedHashesWithNDegreeHashing(): void
    {
        $dataset = (new Parser())->parse(
            "_:e0 <http://a/p> _:e1 .\n_:e0 <http://a/p> _:e2 .\n_:e1 <http://a/p> _:e3 .\n"
            . "_:e2 <http://a/p> _:e4 .\n_:e3 <http://a/q> \"x\" .\n_:e4 <http://a/q> \"y\" .\n",
        );

        $result = (new Canonicalizer('sha256', 2))->canonicalize($dataset);

        $this->assertSame(
            "_:c14n0 <http://a/q> \"x\" .\n"
            . "_:c14n1 <http://a/q> \"y\" .\n"
            . "_:c14n2 <http://a/p> _:c14n3 .\n"
            . "_:c14n2 <http://a/p> _:c14n4 .\n"
            . "_:c14n3 <http://a/p> _:c14n0 .\n"
            . "_:c14n4 <http://a/p> _:c14n1 .\n",
            $result->toNQuads(),
        );
    }

    public function testDefaultsToAWorkFactorOfOne(): void
    {
        $this->expectException(IterationLimitExceeded::class);
        $this->expectExceptionMessage('Canonicalization exceeded the deep iteration limit of 2');

        (new Canonicalizer())->canonicalize((new Parser())->parse(self::CIRCLE));
    }

    public function testAWorkFactorOfZeroDisablesDeepIterations(): void
    {
        $canonicalizer = new Canonicalizer('sha256', 0);
        $tree = (new Parser())->parse("_:a <http://a/p> _:b .\n_:b <http://a/p> <http://a/o> .\n");

        $this->assertSame(
            "_:c14n0 <http://a/p> _:c14n1 .\n_:c14n1 <http://a/p> <http://a/o> .\n",
            $canonicalizer->canonicalize($tree)->toNQuads(),
        );

        $this->expectException(IterationLimitExceeded::class);
        $this->expectExceptionMessage('Canonicalization exceeded the deep iteration limit of 0');

        $canonicalizer->canonicalize((new Parser())->parse(self::CIRCLE));
    }

    public function testAWorkFactorOfTwoAllowsTheSquareOfTheNonUniqueCount(): void
    {
        $result = (new Canonicalizer('sha256', 2))->canonicalize((new Parser())->parse(self::CIRCLE));

        $this->assertSame(self::CIRCLE_CANONICAL, $result->toNQuads());
        $this->assertSame(['a' => 'c14n0', 'b' => 'c14n1'], $result->issuedIdentifiers());
    }

    public function testAnExplicitLimitOverridesTheWorkFactor(): void
    {
        $result = (new Canonicalizer('sha256', 0, 4))->canonicalize((new Parser())->parse(self::CIRCLE));

        $this->assertSame(self::CIRCLE_CANONICAL, $result->toNQuads());

        $this->expectException(IterationLimitExceeded::class);
        $this->expectExceptionMessage('Canonicalization exceeded the deep iteration limit of 3');

        (new Canonicalizer('sha256', 3, 3))->canonicalize((new Parser())->parse(self::CIRCLE));
    }

    public function testAcceptsAnExplicitLimitOfZero(): void
    {
        $this->expectException(IterationLimitExceeded::class);
        $this->expectExceptionMessage('Canonicalization exceeded the deep iteration limit of 0');

        (new Canonicalizer('sha256', 1, 0))->canonicalize((new Parser())->parse(self::CIRCLE));
    }

    public function testSkipsGroupMembersThatAlreadyHaveACanonicalIdentifier(): void
    {
        // x, y, z1, and z2 share a first degree hash. Resolving the group
        // {w1, w2} first reaches x and y and issues them canonical
        // identifiers, so when the group {x, y, z1, z2} is reached, x and y
        // must be skipped and z1 and z2 still ordered by their hashes.
        $dataset = (new Parser())->parse(
            "_:w1 <http://a/p> _:x .\n_:w2 <http://a/p> _:y .\n_:u <http://a/p> _:z1 .\n_:u <http://a/p> _:z2 .\n"
            . "_:x <http://a/s> _:m3 .\n_:y <http://a/s> _:m4 .\n_:z1 <http://a/s> _:m1 .\n_:z2 <http://a/s> _:m2 .\n"
            . "_:m1 <http://a/r> \"2\" .\n_:m2 <http://a/r> \"1\" .\n"
            . "_:m3 <http://a/r> \"3\" .\n_:m4 <http://a/r> \"4\" .\n",
        );

        $result = (new Canonicalizer('sha256', 3))->canonicalize($dataset);

        $this->assertSame(
            "_:c14n0 <http://a/r> \"2\" .\n"
            . "_:c14n1 <http://a/r> \"1\" .\n"
            . "_:c14n10 <http://a/s> _:c14n0 .\n"
            . "_:c14n2 <http://a/r> \"4\" .\n"
            . "_:c14n3 <http://a/r> \"3\" .\n"
            . "_:c14n4 <http://a/p> _:c14n10 .\n"
            . "_:c14n4 <http://a/p> _:c14n9 .\n"
            . "_:c14n5 <http://a/p> _:c14n6 .\n"
            . "_:c14n6 <http://a/s> _:c14n3 .\n"
            . "_:c14n7 <http://a/p> _:c14n8 .\n"
            . "_:c14n8 <http://a/s> _:c14n2 .\n"
            . "_:c14n9 <http://a/s> _:c14n1 .\n",
            $result->toNQuads(),
        );
        $this->assertSame(
            [
                'm1' => 'c14n0',
                'm2' => 'c14n1',
                'm4' => 'c14n2',
                'm3' => 'c14n3',
                'u' => 'c14n4',
                'w1' => 'c14n5',
                'x' => 'c14n6',
                'w2' => 'c14n7',
                'y' => 'c14n8',
                'z2' => 'c14n9',
                'z1' => 'c14n10',
            ],
            $result->issuedIdentifiers(),
        );
    }

    public function testTreatsAnOverflowingLimitAsUnlimited(): void
    {
        // 2 ** 70 does not fit in an integer.
        $result = (new Canonicalizer('sha256', 70))->canonicalize((new Parser())->parse(self::CIRCLE));

        $this->assertSame(self::CIRCLE_CANONICAL, $result->toNQuads());
    }

    public function testUsesTheRequestedHashAlgorithm(): void
    {
        $dataset = (new Parser())->parse(self::DIAMOND);

        $sha256 = (new Canonicalizer())->canonicalize($dataset);
        $sha384 = (new Canonicalizer('sha384'))->canonicalize($dataset);

        $this->assertSame(
            "<http://example.org/vocab#test> <http://example.org/vocab#A> _:c14n2 .\n"
            . "<http://example.org/vocab#test> <http://example.org/vocab#B> _:c14n0 .\n"
            . "_:c14n0 <http://example.org/vocab#next> _:c14n1 .\n"
            . "_:c14n2 <http://example.org/vocab#next> _:c14n1 .\n",
            $sha256->toNQuads(),
        );
        $this->assertSame(['e1' => 'c14n0', 'e2' => 'c14n1', 'e0' => 'c14n2'], $sha256->issuedIdentifiers());
        $this->assertSame(
            "<http://example.org/vocab#test> <http://example.org/vocab#A> _:c14n0 .\n"
            . "<http://example.org/vocab#test> <http://example.org/vocab#B> _:c14n2 .\n"
            . "_:c14n0 <http://example.org/vocab#next> _:c14n1 .\n"
            . "_:c14n2 <http://example.org/vocab#next> _:c14n1 .\n",
            $sha384->toNQuads(),
        );
        $this->assertSame(['e0' => 'c14n0', 'e2' => 'c14n1', 'e1' => 'c14n2'], $sha384->issuedIdentifiers());
    }

    public function testCanBeReused(): void
    {
        $canonicalizer = new Canonicalizer();
        $dataset = (new Parser())->parse(self::DIAMOND);

        $first = $canonicalizer->canonicalize($dataset);
        $second = $canonicalizer->canonicalize($dataset);

        $this->assertSame($first->toNQuads(), $second->toNQuads());
        $this->assertSame($first->issuedIdentifiers(), $second->issuedIdentifiers());
    }
}
