<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Exception;

use RuntimeException;
use SocialWeb\Rdf\Exception\IterationLimitExceeded;
use SocialWeb\Rdf\Exception\RdfException;
use SocialWeb\Test\Rdf\TestCase;

class IterationLimitExceededTest extends TestCase
{
    public function testCanBeCaughtAsAnRdfException(): void
    {
        $this->expectException(RdfException::class);

        throw new IterationLimitExceeded(1);
    }

    public function testCanBeCaughtAsARuntimeException(): void
    {
        $this->expectException(RuntimeException::class);

        throw new IterationLimitExceeded(1);
    }

    public function testCarriesTheLimit(): void
    {
        $exception = new IterationLimitExceeded(42);

        $this->assertSame(42, $exception->limit);
        $this->assertSame('Canonicalization exceeded the deep iteration limit of 42', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testAcceptsPreviousThrowable(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new IterationLimitExceeded(1, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
