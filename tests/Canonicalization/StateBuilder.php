<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Canonicalization\CanonicalizationState;
use SocialWeb\Rdf\NQuads\Parser;
use SocialWeb\Rdf\NQuads\Serializer;

use function in_array;

/**
 * Builds a canonicalization state from an N-Quads document, the way step 2
 * of the canonicalization algorithm does, so the hashing steps can be tested
 * on their own
 */
final class StateBuilder
{
    public static function fromNQuads(
        string $document,
        string $hashAlgorithm = 'sha256',
        int $deepIterations = 100,
    ): CanonicalizationState {
        $state = new CanonicalizationState($hashAlgorithm, new Serializer());

        foreach ((new Parser())->parse($document) as $quad) {
            $seen = [];

            foreach ([$quad->subject, $quad->object, $quad->graph] as $component) {
                if (!$component instanceof BlankNode || in_array($component->identifier, $seen, true)) {
                    continue;
                }

                $seen[] = $component->identifier;
                $state->blankNodeToQuads[$component->identifier][] = $quad;
            }
        }

        $state->deepIterationLimit = $deepIterations;
        $state->remainingDeepIterations = $deepIterations;

        return $state;
    }
}
