<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\Canonicalization\CanonicalizationState;
use SocialWeb\Rdf\HashAlgorithm;
use SocialWeb\Test\Rdf\TestCase;

final class CanonicalizationStateTest extends TestCase
{
    public function testStartsEmptyWithACanonicalIssuer(): void
    {
        $state = new CanonicalizationState(HashAlgorithm::Sha256);

        $this->assertSame(HashAlgorithm::Sha256, $state->hashAlgorithm);
        $this->assertSame([], $state->blankNodeToQuads);
        $this->assertSame([], $state->hashToBlankNodes);
        $this->assertSame([], $state->firstDegreeHashes);
        $this->assertSame(0, $state->deepIterationLimit);
        $this->assertSame(0, $state->remainingDeepIterations);
        $this->assertSame('c14n0', $state->canonicalIssuer->issue('e0'));
    }

    public function testHashesWithTheConfiguredAlgorithmAsLowercaseHexadecimal(): void
    {
        $this->assertSame(
            'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            (new CanonicalizationState(HashAlgorithm::Sha256))->hash('abc'),
        );
        $this->assertSame(
            'cb00753f45a35e8bb5a03d699ac65007272c32ab0eded1631a8b605a43ff5bed8086072ba1e7cc2358baeca134c825a7',
            (new CanonicalizationState(HashAlgorithm::Sha384))->hash('abc'),
        );
    }
}
