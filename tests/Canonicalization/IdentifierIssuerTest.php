<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use SocialWeb\Rdf\Canonicalization\IdentifierIssuer;
use SocialWeb\Test\Rdf\TestCase;

class IdentifierIssuerTest extends TestCase
{
    public function testIssuesIdentifiersFromThePrefixAndACounter(): void
    {
        $issuer = new IdentifierIssuer('c14n');

        $this->assertSame('c14n0', $issuer->issue('e2'));
        $this->assertSame('c14n1', $issuer->issue('e0'));
        $this->assertSame('c14n2', $issuer->issue('e1'));
    }

    public function testReturnsTheSameIdentifierForAnExistingIdentifier(): void
    {
        $issuer = new IdentifierIssuer('b');

        $this->assertSame('b0', $issuer->issue('x'));
        $this->assertSame('b0', $issuer->issue('x'));
        $this->assertSame('b1', $issuer->issue('y'));
        $this->assertSame('b0', $issuer->issue('x'));
    }

    public function testReportsWhetherAnIdentifierHasBeenIssued(): void
    {
        $issuer = new IdentifierIssuer('b');

        $this->assertFalse($issuer->hasIssued('x'));

        $issuer->issue('x');

        $this->assertTrue($issuer->hasIssued('x'));
        $this->assertFalse($issuer->hasIssued('b0'));
    }

    public function testListsIssuedIdentifiersInIssueOrder(): void
    {
        $issuer = new IdentifierIssuer('c14n');

        $this->assertSame([], $issuer->issued());

        $issuer->issue('z');
        $issuer->issue('a');
        $issuer->issue('z');
        $issuer->issue('m');

        $this->assertSame(['z' => 'c14n0', 'a' => 'c14n1', 'm' => 'c14n2'], $issuer->issued());
    }

    public function testStoresNumericIdentifiersAsIntegerKeys(): void
    {
        $issuer = new IdentifierIssuer('c14n');
        $issuer->issue('0');
        $issuer->issue('10');

        $this->assertTrue($issuer->hasIssued('10'));
        $this->assertSame('c14n1', $issuer->issue('10'));
        $this->assertSame([0 => 'c14n0', 10 => 'c14n1'], $issuer->issued());
    }

    public function testCopiesAreIndependent(): void
    {
        $issuer = new IdentifierIssuer('b');
        $issuer->issue('x');

        $copy = $issuer->copy();
        $copy->issue('y');
        $issuer->issue('z');

        $this->assertSame(['x' => 'b0', 'y' => 'b1'], $copy->issued());
        $this->assertSame(['x' => 'b0', 'z' => 'b1'], $issuer->issued());
        $this->assertFalse($issuer->hasIssued('y'));
        $this->assertFalse($copy->hasIssued('z'));
    }
}
