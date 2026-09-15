<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\Exception\IterationLimitExceeded;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Test\Rdf\TestCase;

use function json_decode;
use function ksort;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

/**
 * Runs the W3C RDF Dataset Canonicalization (RDFC-1.0) test suite
 *
 * Each entry runs with the work factor the reference implementation's
 * harness uses for its computational complexity: 0 for low, 2 for medium,
 * and 3 for high.
 *
 * @link https://w3c.github.io/rdf-canon/tests/
 */
class W3cCanonicalizationTest extends TestCase
{
    #[DataProvider('evaluationTests')]
    public function testEvaluation(string $action, ?string $result, string $hashAlgorithm, int $workFactor): void
    {
        $this->assertNotNull($result);
        $dataset = (new Parser())->parse(W3cManifest::read($action));

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

    #[DataProvider('mapTests')]
    public function testIssuedIdentifiersMap(
        string $action,
        ?string $result,
        string $hashAlgorithm,
        int $workFactor,
    ): void {
        $this->assertNotNull($result);
        $expected = json_decode(W3cManifest::read($result), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($expected);
        $dataset = (new Parser())->parse(W3cManifest::read($action));

        $actual = (new Canonicalizer($hashAlgorithm, $workFactor))->canonicalize($dataset)->issuedIdentifiers();

        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, string | null, string, int}>
     */
    public static function mapTests(): iterable
    {
        return W3cManifest::entries(W3cManifest::MAP);
    }

    #[DataProvider('negativeTests')]
    public function testNegativeEvaluation(
        string $action,
        ?string $result,
        string $hashAlgorithm,
        int $workFactor,
    ): void {
        $this->assertNull($result);
        $dataset = (new Parser())->parse(W3cManifest::read($action));

        $this->expectException(IterationLimitExceeded::class);

        (new Canonicalizer($hashAlgorithm, $workFactor))->canonicalize($dataset);
    }

    /**
     * @return iterable<string, array{string, string | null, string, int}>
     */
    public static function negativeTests(): iterable
    {
        return W3cManifest::entries(W3cManifest::NEGATIVE);
    }

    #[DataProvider('negativeTests')]
    public function testTheNegativeTestAlsoFailsWithTheDefaultWorkFactor(
        string $action,
        ?string $result,
        string $hashAlgorithm,
        int $workFactor,
    ): void {
        $dataset = (new Parser())->parse(W3cManifest::read($action));

        $this->expectException(IterationLimitExceeded::class);

        (new Canonicalizer())->canonicalize($dataset);
    }
}
