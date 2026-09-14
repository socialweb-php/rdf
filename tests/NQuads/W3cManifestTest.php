<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use RuntimeException;
use SocialWeb\Test\Rdf\TestCase;

use function iterator_to_array;

class W3cManifestTest extends TestCase
{
    public function testListsEveryPositiveSyntaxTest(): void
    {
        $entries = iterator_to_array(W3cManifest::entries(W3cManifest::POSITIVE));

        $this->assertCount(53, $entries);
        $this->assertArrayHasKey('nq-syntax-uri-01', $entries);
        $this->assertSame([W3cManifest::FIXTURES . '/nq-syntax-uri-01.nq'], $entries['nq-syntax-uri-01']);
        $this->assertArrayHasKey('comment_following_triple', $entries);
    }

    public function testListsEveryNegativeSyntaxTest(): void
    {
        $entries = iterator_to_array(W3cManifest::entries(W3cManifest::NEGATIVE));

        $this->assertCount(34, $entries);
        $this->assertArrayHasKey('nq-syntax-bad-quint-01', $entries);
        $this->assertSame([W3cManifest::FIXTURES . '/nq-syntax-bad-quint-01.nq'], $entries['nq-syntax-bad-quint-01']);
    }

    public function testEveryActionFileExists(): void
    {
        foreach ([W3cManifest::POSITIVE, W3cManifest::NEGATIVE] as $type) {
            foreach (W3cManifest::entries($type) as $name => [$path]) {
                $this->assertFileExists($path, $name);
            }
        }
    }

    public function testReadFailsLoudlyForAMissingFixture(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read fixture');

        W3cManifest::read(W3cManifest::FIXTURES . '/does-not-exist.nq');
    }
}
