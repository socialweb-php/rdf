<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\Canonicalization\IdentifierIssuer;
use SocialWeb\Rdf\Canonicalization\NDegreeHash;
use SocialWeb\Test\Rdf\TestCase;

class NDegreeHashTest extends TestCase
{
    public function testCarriesTheHashAndTheIssuer(): void
    {
        $issuer = new IdentifierIssuer('b');
        $result = new NDegreeHash('abc', $issuer);

        $this->assertSame('abc', $result->hash);
        $this->assertSame($issuer, $result->issuer);
    }
}
