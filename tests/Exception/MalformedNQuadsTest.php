<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Exception;

use RuntimeException;
use SocialWeb\Rdf\Exception\MalformedNQuads;
use SocialWeb\Rdf\Exception\RdfException;
use SocialWeb\Test\Rdf\TestCase;

class MalformedNQuadsTest extends TestCase
{
    public function testCanBeCaughtAsAnRdfException(): void
    {
        $this->expectException(RdfException::class);

        throw new MalformedNQuads(1, 1, '', 'reason');
    }

    public function testCanBeCaughtAsARuntimeException(): void
    {
        $this->expectException(RuntimeException::class);

        throw new MalformedNQuads(1, 1, '', 'reason');
    }

    public function testCarriesPositionAndOffendingLine(): void
    {
        $exception = new MalformedNQuads(3, 12, '<a> <b> "oops .', 'unterminated string literal');

        $this->assertSame(3, $exception->lineNumber);
        $this->assertSame(12, $exception->columnNumber);
        $this->assertSame('<a> <b> "oops .', $exception->offendingLine);
        $this->assertSame(
            'Malformed N-Quads at line 3, column 12: unterminated string literal. Offending line: <a> <b> "oops .',
            $exception->getMessage(),
        );
    }

    public function testAcceptsPreviousThrowable(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new MalformedNQuads(1, 1, '', 'reason', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
