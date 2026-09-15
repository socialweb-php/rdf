<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\Canonicalization\HashFirstDegreeQuads;
use SocialWeb\Test\Rdf\TestCase;

class HashFirstDegreeQuadsTest extends TestCase
{
    /**
     * The "unique hashes" example from RDFC-1.0 section 4.4.3, whose logged
     * first degree hashes are asserted here
     */
    private const string UNIQUE_HASHES_EXAMPLE = "<http://example.com/#p> <http://example.com/#q> _:e0 .\n"
        . "_:e0 <http://example.com/#s> <http://example.com/#u> .\n"
        . "<http://example.com/#p> <http://example.com/#r> _:e1 .\n"
        . "_:e1 <http://example.com/#t> <http://example.com/#u> .\n";

    public function testHashesTheSpecificationExample(): void
    {
        $state = StateBuilder::fromNQuads(self::UNIQUE_HASHES_EXAMPLE);
        $hasher = new HashFirstDegreeQuads($state);

        $this->assertSame('21d1dd5ba21f3dee9d76c0c00c260fa6f5d5d65315099e553026f4828d0dc77a', $hasher->hash('e0'));
        $this->assertSame('6fa0b9bdb376852b5743ff39ca4cbf7ea14d34966b2828478fbf222e7c764473', $hasher->hash('e1'));
    }

    public function testSortsTheLinesBeforeHashing(): void
    {
        $reversed = "_:e0 <http://example.com/#s> <http://example.com/#u> .\n"
            . "<http://example.com/#p> <http://example.com/#q> _:e0 .\n";
        $state = StateBuilder::fromNQuads($reversed);

        $this->assertSame(
            '21d1dd5ba21f3dee9d76c0c00c260fa6f5d5d65315099e553026f4828d0dc77a',
            (new HashFirstDegreeQuads($state))->hash('e0'),
        );
    }

    public function testWritesTheReferenceNodeAsAAndEveryOtherBlankNodeAsZInEveryPosition(): void
    {
        $state = StateBuilder::fromNQuads(
            "_:e0 <http://a/p> _:e1 _:e2 .\n_:e1 <http://a/p> _:e0 .\n_:e1 <http://a/p> \"x\" _:e0 .\n",
        );

        // sha256 of "_:a <http://a/p> _:z _:z .\n_:z <http://a/p> \"x\" _:a .\n_:z <http://a/p> _:a .\n"
        $this->assertSame(
            '6b383f0dd22bd3ba002409edb28ef9627a2a783797488091cc19b6979c54ed3d',
            (new HashFirstDegreeQuads($state))->hash('e0'),
        );
    }

    public function testCachesTheHashInTheState(): void
    {
        $state = StateBuilder::fromNQuads(self::UNIQUE_HASHES_EXAMPLE);
        $hasher = new HashFirstDegreeQuads($state);

        $this->assertSame([], $state->firstDegreeHashes);

        $hash = $hasher->hash('e0');

        $this->assertSame(['e0' => $hash], $state->firstDegreeHashes);

        // A cached hash is returned even if the quads have since changed.
        $state->blankNodeToQuads['e0'] = [];

        $this->assertSame($hash, $hasher->hash('e0'));
    }
}
