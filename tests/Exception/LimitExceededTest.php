<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Exception;

use RuntimeException;
use SocialWeb\Rdf\Exception\LimitExceeded;
use SocialWeb\Rdf\Exception\RdfException;
use SocialWeb\Test\Rdf\TestCase;

final class LimitExceededTest extends TestCase
{
    public function testCanBeCaughtAsAnRdfException(): void
    {
        $this->expectException(RdfException::class);

        throw new LimitExceeded('maxDeepIterations', 1);
    }

    public function testCanBeCaughtAsARuntimeException(): void
    {
        $this->expectException(RuntimeException::class);

        throw new LimitExceeded('maxDeepIterations', 1);
    }

    public function testHasTheParameterAndTheLimit(): void
    {
        $exception = new LimitExceeded(parameter: 'maxDeepIterations', limit: 42);

        $this->assertSame('maxDeepIterations', $exception->parameter);
        $this->assertSame(42, $exception->limit);
        $this->assertSame('The maxDeepIterations limit of 42 was exceeded', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testAcceptsPreviousThrowable(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new LimitExceeded('maxDeepIterations', 1, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
