<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use RuntimeException;
use SocialWeb\Test\Rdf\TestCase;

use function iterator_to_array;

class W3cManifestTest extends TestCase
{
    public function testListsEveryEvaluationTest(): void
    {
        $entries = iterator_to_array(W3cManifest::entries(W3cManifest::EVAL));

        $this->assertCount(64, $entries);
        $this->assertArrayHasKey('test001c', $entries);
        $this->assertSame(
            [
                W3cManifest::FIXTURES . '/rdfc10/test001-in.nq',
                W3cManifest::FIXTURES . '/rdfc10/test001-rdfc10.nq',
                'sha256',
                0,
            ],
            $entries['test001c'],
        );
        $this->assertArrayHasKey('test021c', $entries);
        $this->assertSame(2, $entries['test021c'][3]);
        $this->assertArrayHasKey('test044c', $entries);
        $this->assertSame(3, $entries['test044c'][3]);
        $this->assertArrayHasKey('test075c', $entries);
        $this->assertSame('sha384', $entries['test075c'][2]);
    }

    public function testListsEveryMapTest(): void
    {
        $entries = iterator_to_array(W3cManifest::entries(W3cManifest::MAP));

        $this->assertCount(21, $entries);
        $this->assertArrayHasKey('test003m', $entries);
        $this->assertSame(
            [
                W3cManifest::FIXTURES . '/rdfc10/test003-in.nq',
                W3cManifest::FIXTURES . '/rdfc10/test003-rdfc10map.json',
                'sha256',
                0,
            ],
            $entries['test003m'],
        );
        $this->assertArrayHasKey('test075m', $entries);
        $this->assertSame('sha384', $entries['test075m'][2]);
    }

    public function testListsTheNegativeTest(): void
    {
        $entries = iterator_to_array(W3cManifest::entries(W3cManifest::NEGATIVE));

        $this->assertSame(
            ['test074c' => [W3cManifest::FIXTURES . '/rdfc10/test074-in.nq', null, 'sha256', 3]],
            $entries,
        );
    }

    public function testEveryActionAndResultFileExists(): void
    {
        foreach ([W3cManifest::EVAL, W3cManifest::MAP, W3cManifest::NEGATIVE] as $type) {
            foreach (W3cManifest::entries($type) as $name => [$action, $result]) {
                $this->assertFileExists($action, $name);

                if ($result !== null) {
                    $this->assertFileExists($result, $name);
                }
            }
        }
    }

    public function testRejectsAnUnknownComplexity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown computational complexity "extreme"');

        W3cManifest::workFactor('extreme');
    }

    public function testReadFailsLoudlyForAMissingFixture(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read fixture');

        W3cManifest::read(W3cManifest::FIXTURES . '/does-not-exist.nq');
    }
}
