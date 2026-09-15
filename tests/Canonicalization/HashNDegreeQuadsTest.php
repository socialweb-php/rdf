<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\Canonicalization\CanonicalizationState;
use SocialWeb\Rdf\Canonicalization\HashFirstDegreeQuads;
use SocialWeb\Rdf\Canonicalization\HashNDegreeQuads;
use SocialWeb\Rdf\Canonicalization\HashRelatedBlankNode;
use SocialWeb\Rdf\Canonicalization\IdentifierIssuer;
use SocialWeb\Rdf\Exception\IterationLimitExceeded;
use SocialWeb\Test\Rdf\TestCase;

use function str_split;

class HashNDegreeQuadsTest extends TestCase
{
    /**
     * The "double circle" example from RDFC-1.0 section 4.8, whose logged
     * result is asserted here
     */
    private const string DOUBLE_CIRCLE = "_:e0 <http://example.org/vocab#next> _:e1 .\n"
        . "_:e0 <http://example.org/vocab#prev> _:e1 .\n"
        . "_:e1 <http://example.org/vocab#next> _:e0 .\n"
        . "_:e1 <http://example.org/vocab#prev> _:e0 .\n";

    /**
     * e1 and e2 have the same first degree hash, so they share a related
     * hash under e0 and both orders of them must be tried; e3 and e4 differ,
     * so the two orders give different paths
     */
    private const string SHARED_RELATED_HASH = "_:e0 <http://a/p> _:e1 .\n"
        . "_:e0 <http://a/p> _:e2 .\n"
        . "_:e1 <http://a/p> _:e3 .\n"
        . "_:e2 <http://a/p> _:e4 .\n"
        . "_:e3 <http://a/q> \"x\" .\n"
        . "_:e4 <http://a/q> \"y\" .\n";

    public function testHashesTheDoubleCircleExampleFromTheSpecification(): void
    {
        $state = StateBuilder::fromNQuads(self::DOUBLE_CIRCLE);
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($state)->hash('e0', $issuer);

        $this->assertSame('e332b4b59e1c4794ee72a4df0f63723326ffb6d6a5c0d0cb4d2dd8d8d5ebf5a4', $result->hash);
        $this->assertSame(['e0' => 'b0', 'e1' => 'b1'], $result->issuer->issued());
    }

    public function testCountsEveryCallIncludingRecursionAgainstTheBudget(): void
    {
        $state = StateBuilder::fromNQuads(self::DOUBLE_CIRCLE);
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        self::hasher($state)->hash('e0', $issuer);

        // One call for e0 and one recursive call for e1. The second related
        // hash of e0 finds e1 already issued by the chosen issuer, so it does
        // not recurse again.
        $this->assertSame(98, $state->remainingDeepIterations);
    }

    public function testChoosesTheLeastPathAmongPermutationsOfRelatedNodes(): void
    {
        $state = StateBuilder::fromNQuads(self::SHARED_RELATED_HASH);
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($state)->hash('e0', $issuer);

        $this->assertSame('5df7fc1cd3297bef0755e68aef1804454834438572cce2492d70dc44fdfa2fb7', $result->hash);
        $this->assertSame(
            ['e0' => 'b0', 'e2' => 'b1', 'e1' => 'b2', 'e4' => 'b3', 'e3' => 'b4'],
            $result->issuer->issued(),
        );
        $this->assertSame(91, $state->remainingDeepIterations);
    }

    public function testAppendsEveryOccurrenceOfARelatedNodeThatHasACanonicalIdentifier(): void
    {
        // e1 is related to e0 twice in the same position with the same
        // predicate, so it appears twice in one related-hash group.
        $state = StateBuilder::fromNQuads("_:e0 <http://a/p> _:e1 .\n_:e0 <http://a/p> _:e1 <http://a/g> .\n");
        $state->canonicalIssuer->issue('e1');
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($state)->hash('e0', $issuer);

        // The path is "_:c14n0_:c14n0", once per occurrence.
        $this->assertSame('cdf08464210f6f4baa26f3f75447b0da65a42ac029d74a5a73f7ff6e6e6220ea', $result->hash);
        $this->assertSame(['e0' => 'b0'], $result->issuer->issued());
        $this->assertSame(99, $state->remainingDeepIterations);
    }

    public function testSortsRelatedNodesGivenInDescendingDocumentOrder(): void
    {
        // e1 and e2 are related to e0 in descending document order, so the
        // sort() at the top of permutations() must reorder them before the
        // first permutation is tried.
        $state = StateBuilder::fromNQuads(
            "_:e0 <http://a/p> _:e2 .\n_:e0 <http://a/p> _:e1 .\n"
            . "_:e1 <http://a/q> \"x\" .\n_:e2 <http://a/q> \"y\" .\n",
        );
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($state)->hash('e0', $issuer);

        // Computed with the implementation.
        $this->assertSame('5a546283a660a3d15769f44eb260adf6a6dd3fac47df81756d1542cffd41bff6', $result->hash);
        $this->assertSame(['e0' => 'b0', 'e1' => 'b1', 'e2' => 'b2'], $result->issuer->issued());
        $this->assertSame(97, $state->remainingDeepIterations);
    }

    public function testKeepsTheFirstOfTwoPermutationsWithEqualPaths(): void
    {
        // e1 and e2 cannot be told apart, so both orders give the same path
        // and the first order, e1 before e2, must win.
        $state = StateBuilder::fromNQuads("_:e0 <http://a/p> _:e1 .\n_:e0 <http://a/p> _:e2 .\n");
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($state)->hash('e0', $issuer);

        $this->assertSame('052075309f4f2c73674ab7361873f7e52e604a5349dd07827aeeb238e474517a', $result->hash);
        $this->assertSame(['e0' => 'b0', 'e1' => 'b1', 'e2' => 'b2'], $result->issuer->issued());
        $this->assertSame(95, $state->remainingDeepIterations);
    }

    public function testPermutesRelatedNodesThatAppearMoreThanOnce(): void
    {
        $pairs = StateBuilder::fromNQuads(
            "_:e0 <http://a/p> _:e1 .\n_:e0 <http://a/p> _:e1 <http://a/g> .\n"
            . "_:e0 <http://a/p> _:e2 .\n_:e0 <http://a/p> _:e2 <http://a/g> .\n",
        );
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($pairs)->hash('e0', $issuer);

        // The related list is [e1, e1, e2, e2]: six distinct permutations.
        $this->assertSame('1aa0e19035b6d211f5aac93fa9fc8ae1cf90e2f4c0f036647ff4151b3667930b', $result->hash);
        $this->assertSame(['e0' => 'b0', 'e1' => 'b1', 'e2' => 'b2'], $result->issuer->issued());
        $this->assertSame(95, $pairs->remainingDeepIterations);

        $triples = StateBuilder::fromNQuads(
            "_:e0 <http://a/p> _:e1 .\n_:e0 <http://a/p> _:e1 <http://a/g> .\n"
            . "_:e0 <http://a/p> _:e2 .\n_:e0 <http://a/p> _:e2 <http://a/g> .\n"
            . "_:e0 <http://a/p> _:e3 .\n_:e0 <http://a/p> _:e3 <http://a/g> .\n",
        );
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($triples)->hash('e0', $issuer);

        // The related list is [e1, e1, e2, e2, e3, e3]: ninety distinct permutations.
        $this->assertSame('f26761f12b8ce557f873c5cb3f120a587e4b7f48de86853de79a9225f31fdf40', $result->hash);
        $this->assertSame(['e0' => 'b0', 'e1' => 'b1', 'e2' => 'b2', 'e3' => 'b3'], $result->issuer->issued());
        $this->assertSame(81, $triples->remainingDeepIterations);
    }

    /**
     * @param array<string, string> $issued
     */
    #[DataProvider('threeRelatedNodes')]
    public function testTriesEveryPermutationOfThreeRelatedNodes(
        string $literals,
        array $issued,
        int $remaining,
    ): void {
        // e1, e2, and e3 share a related hash under e0 and differ only two
        // steps away, so the six permutations give six different paths and
        // pruning decides how many recursive calls are made.
        [$x, $y, $z] = str_split($literals);
        $state = StateBuilder::fromNQuads(
            "_:e0 <http://a/p> _:e1 .\n_:e0 <http://a/p> _:e2 .\n_:e0 <http://a/p> _:e3 .\n"
            . "_:e1 <http://a/p> _:e4 .\n_:e2 <http://a/p> _:e5 .\n_:e3 <http://a/p> _:e6 .\n"
            . "_:e4 <http://a/q> \"$x\" .\n_:e5 <http://a/q> \"$y\" .\n_:e6 <http://a/q> \"$z\" .\n",
        );
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $result = self::hasher($state)->hash('e0', $issuer);

        $this->assertSame('b5c33d50186e99bc6426ade269913f977f8125a9e36f6d7ba7b5f8ef12e40c56', $result->hash);
        $this->assertSame($issued, $result->issuer->issued());
        $this->assertSame($remaining, $state->remainingDeepIterations);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, int}>
     */
    public static function threeRelatedNodes(): iterable
    {
        yield 'x y z' => [
            'xyz',
            ['e0' => 'b0', 'e1' => 'b1', 'e3' => 'b2', 'e2' => 'b3', 'e4' => 'b4', 'e6' => 'b5', 'e5' => 'b6'],
            79,
        ];
        yield 'z y x' => [
            'zyx',
            ['e0' => 'b0', 'e3' => 'b1', 'e1' => 'b2', 'e2' => 'b3', 'e6' => 'b4', 'e4' => 'b5', 'e5' => 'b6'],
            65,
        ];
    }

    public function testRaisesWhenTheBudgetIsSpent(): void
    {
        $state = StateBuilder::fromNQuads(self::DOUBLE_CIRCLE);
        $state->deepIterationLimit = 7;
        $state->remainingDeepIterations = 0;

        $this->expectException(IterationLimitExceeded::class);
        $this->expectExceptionMessage('Canonicalization exceeded the deep iteration limit of 7');

        self::hasher($state)->hash('e0', new IdentifierIssuer('b'));
    }

    public function testRaisesFromARecursiveCallWhenTheBudgetIsSpent(): void
    {
        $state = StateBuilder::fromNQuads(self::DOUBLE_CIRCLE, deepIterations: 1);
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('e0');

        $this->expectException(IterationLimitExceeded::class);
        $this->expectExceptionMessage('Canonicalization exceeded the deep iteration limit of 1');

        self::hasher($state)->hash('e0', $issuer);
    }

    private static function hasher(CanonicalizationState $state): HashNDegreeQuads
    {
        return new HashNDegreeQuads($state, new HashRelatedBlankNode($state, new HashFirstDegreeQuads($state)));
    }
}
