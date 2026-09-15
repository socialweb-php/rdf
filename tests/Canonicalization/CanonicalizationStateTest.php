<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\Canonicalization\CanonicalizationState;
use SocialWeb\Rdf\NQuads\Serializer;
use SocialWeb\Test\Rdf\TestCase;

class CanonicalizationStateTest extends TestCase
{
    public function testStartsEmptyWithACanonicalIssuer(): void
    {
        $serializer = new Serializer();
        $state = new CanonicalizationState('sha256', $serializer);

        $this->assertSame('sha256', $state->hashAlgorithm);
        $this->assertSame($serializer, $state->serializer);
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
            (new CanonicalizationState('sha256', new Serializer()))->hash('abc'),
        );
        $this->assertSame(
            'cb00753f45a35e8bb5a03d699ac65007272c32ab0eded1631a8b605a43ff5bed8086072ba1e7cc2358baeca134c825a7',
            (new CanonicalizationState('sha384', new Serializer()))->hash('abc'),
        );
    }
}
