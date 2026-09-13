<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Exception;

use InvalidArgumentException;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Exception\RdfException;
use SocialWeb\Test\Rdf\TestCase;

class InvalidArgumentTest extends TestCase
{
    public function testCanBeCaughtAsAnRdfException(): void
    {
        $this->expectException(RdfException::class);

        throw new InvalidArgument('bad input');
    }

    public function testCanBeCaughtAsAnInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        throw new InvalidArgument('bad input');
    }

    public function testCarriesTheMessage(): void
    {
        $this->assertSame('bad input', (new InvalidArgument('bad input'))->getMessage());
    }
}
