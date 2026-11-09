<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Exception;

use RuntimeException;
use SocialWeb\Rdf\Exception\MalformedNQuads;
use SocialWeb\Rdf\Exception\MalformedNQuadsReason;
use SocialWeb\Rdf\Exception\RdfException;
use SocialWeb\Test\Rdf\TestCase;
use UnexpectedValueException;

final class MalformedNQuadsTest extends TestCase
{
    public function testCanBeCaughtAsAnRdfException(): void
    {
        $this->expectException(RdfException::class);

        throw new MalformedNQuads(MalformedNQuadsReason::InvalidSubject, 1, 1, '', 'reason');
    }

    public function testCanBeCaughtAsAnUnexpectedValueException(): void
    {
        $this->expectException(UnexpectedValueException::class);

        throw new MalformedNQuads(MalformedNQuadsReason::InvalidSubject, 1, 1, '', 'reason');
    }

    public function testHasTheReasonThePositionAndTheOffendingLine(): void
    {
        $exception = new MalformedNQuads(
            MalformedNQuadsReason::MalformedStringLiteral,
            3,
            12,
            '<a> <b> "oops .',
            'malformed string literal',
        );

        $this->assertSame(MalformedNQuadsReason::MalformedStringLiteral, $exception->reason);
        $this->assertSame(3, $exception->lineNumber);
        $this->assertSame(12, $exception->columnNumber);
        $this->assertSame('<a> <b> "oops .', $exception->offendingLine);
        $this->assertSame(
            'Malformed N-Quads at line 3, column 12: malformed string literal; '
            . 'the offending line is "<a> <b> "oops ."',
            $exception->getMessage(),
        );
        $this->assertSame(0, $exception->getCode());
    }

    public function testAcceptsPreviousThrowable(): void
    {
        $previous = new RuntimeException('cause');
        $exception = new MalformedNQuads(MalformedNQuadsReason::InvalidIri, 1, 1, '', 'reason', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
