<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Rdf\Quad;
use SocialWeb\Test\Rdf\TestCase;

use function shuffle;

/**
 * Properties of canonicalization, checked over every W3C evaluation test:
 * canonicalizing a canonical dataset changes nothing, and relabeling and
 * reordering the input changes nothing
 */
class PropertyTest extends TestCase
{
    #[DataProvider('evaluationTests')]
    public function testCanonicalizingACanonicalDatasetIsANoOp(
        string $action,
        ?string $result,
        string $hashAlgorithm,
        int $workFactor,
    ): void {
        $this->assertNotNull($result);
        $canonicalizer = new Canonicalizer($hashAlgorithm, $workFactor);
        $canonical = $canonicalizer->canonicalize((new Parser())->parse(W3cManifest::read($action)));

        $again = $canonicalizer->canonicalize($canonical->dataset());

        $this->assertSame(W3cManifest::read($result), $canonical->toNQuads());
        $this->assertSame($canonical->toNQuads(), $again->toNQuads());
    }

    #[DataProvider('evaluationTests')]
    public function testRelabelingAndReorderingTheInputDoesNotChangeTheCanonicalForm(
        string $action,
        ?string $result,
        string $hashAlgorithm,
        int $workFactor,
    ): void {
        $this->assertNotNull($result);
        $dataset = self::relabel((new Parser())->parse(W3cManifest::read($action)));

        $canonical = (new Canonicalizer($hashAlgorithm, $workFactor))->canonicalize($dataset);

        $this->assertSame(W3cManifest::read($result), $canonical->toNQuads());
    }

    /**
     * @return iterable<string, array{string, string | null, string, int}>
     */
    public static function evaluationTests(): iterable
    {
        return W3cManifest::entries(W3cManifest::EVAL);
    }

    /**
     * Gives every blank node a fresh random label and shuffles the quads
     */
    private static function relabel(Dataset $dataset): Dataset
    {
        /** @var array<string, BlankNode> $labels */
        $labels = [];
        $quads = [];

        foreach ($dataset as $quad) {
            $quads[] = new Quad(
                $quad->subject instanceof BlankNode ? self::fresh($quad->subject, $labels) : $quad->subject,
                $quad->predicate,
                $quad->object instanceof BlankNode ? self::fresh($quad->object, $labels) : $quad->object,
                $quad->graph instanceof BlankNode ? self::fresh($quad->graph, $labels) : $quad->graph,
            );
        }

        shuffle($quads);

        return new Dataset($quads);
    }

    /**
     * @param array<string, BlankNode> $labels
     */
    private static function fresh(BlankNode $node, array &$labels): BlankNode
    {
        return $labels[$node->identifier] ??= BlankNode::generate();
    }
}
