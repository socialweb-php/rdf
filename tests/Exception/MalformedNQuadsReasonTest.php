<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Exception;

use SocialWeb\Rdf\Exception\MalformedNQuadsReason;
use SocialWeb\Test\Rdf\TestCase;

use function array_map;

final class MalformedNQuadsReasonTest extends TestCase
{
    public function testHasACaseForEveryWayALineCanBeRefused(): void
    {
        $this->assertSame(
            [
                'InvalidUtf8',
                'InvalidSubject',
                'InvalidPredicate',
                'InvalidObject',
                'MissingTerminator',
                'TextAfterTerminator',
                'MalformedIriReference',
                'InvalidIriEscape',
                'InvalidIri',
                'MalformedBlankNodeLabel',
                'MalformedStringLiteral',
                'InvalidLiteralEscape',
                'MissingDatatype',
                'MalformedLanguageTag',
                'InvalidLiteral',
            ],
            array_map(
                static fn (MalformedNQuadsReason $reason): string => $reason->name,
                MalformedNQuadsReason::cases(),
            ),
        );
    }
}
