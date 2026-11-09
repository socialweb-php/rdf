<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\HashAlgorithm;

use function hash;
use function strlen;

final class HashAlgorithmTest extends TestCase
{
    public function testIsBackedByTheRegistryNames(): void
    {
        $this->assertSame('sha-256', HashAlgorithm::Sha256->value);
        $this->assertSame('sha-384', HashAlgorithm::Sha384->value);
        $this->assertSame('sha-512', HashAlgorithm::Sha512->value);
        $this->assertSame(HashAlgorithm::Sha384, HashAlgorithm::from('sha-384'));
        $this->assertNull(HashAlgorithm::tryFrom('sha256'));
    }

    #[DataProvider('algorithms')]
    public function testNamesTheFunctionAsHashKnowsIt(HashAlgorithm $algorithm, string $hashName, int $length): void
    {
        $this->assertSame($hashName, $algorithm->hashName());
        $this->assertSame($length, strlen(hash($hashName, '', true)));
        $this->assertSame($length, $algorithm->digestLength());
    }

    /**
     * @return iterable<string, array{HashAlgorithm, string, int}>
     */
    public static function algorithms(): iterable
    {
        yield 'SHA-256' => [HashAlgorithm::Sha256, 'sha256', 32];
        yield 'SHA-384' => [HashAlgorithm::Sha384, 'sha384', 48];
        yield 'SHA-512' => [HashAlgorithm::Sha512, 'sha512', 64];
    }
}
