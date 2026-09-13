<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Resource;
use SocialWeb\Rdf\Term;

class IriTest extends TestCase
{
    #[DataProvider('validIris')]
    public function testAcceptsAbsoluteIris(string $value): void
    {
        $iri = new Iri($value);

        $this->assertSame($value, $iri->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validIris(): iterable
    {
        yield 'http' => ['http://example.com/'];
        yield 'https with path, query, and fragment' => ['https://example.com/path?q=1#frag'];
        yield 'urn' => ['urn:uuid:f81d4fae-7dec-11d0-a765-00a0c91e6bf6'];
        yield 'mailto' => ['mailto:someone@example.com'];
        yield 'non-ASCII path' => ['http://example.com/café'];
        yield 'scheme only' => ['a:'];
    }

    #[DataProvider('invalidIris')]
    public function testRejectsInvalidIris(string $value, string $expectedMessageFragment): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage($expectedMessageFragment);

        new Iri($value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIris(): iterable
    {
        yield 'empty' => ['', 'must not be empty'];
        yield 'invalid UTF-8' => ["http://example.com/\xff", 'must be valid UTF-8'];
        yield 'relative path' => ['path/to/thing', 'has no scheme'];
        yield 'absolute path without scheme' => ['/path', 'has no scheme'];
        yield 'scheme starting with a digit' => ['1http://example.com/', 'has no scheme'];
        yield 'space' => ['http://example.com/a b', 'not allowed in an IRI'];
        yield 'angle brackets' => ['http://example.com/<a>', 'not allowed in an IRI'];
        yield 'double quote' => ['http://example.com/"a"', 'not allowed in an IRI'];
        yield 'braces' => ['http://example.com/{a}', 'not allowed in an IRI'];
        yield 'pipe' => ['http://example.com/a|b', 'not allowed in an IRI'];
        yield 'caret' => ['http://example.com/a^b', 'not allowed in an IRI'];
        yield 'backtick' => ['http://example.com/a`b', 'not allowed in an IRI'];
        yield 'backslash' => ['http://example.com/a\\b', 'not allowed in an IRI'];
        yield 'control character' => ["http://example.com/a\x01b", 'not allowed in an IRI'];
    }

    public function testEqualsAnIriWithTheSameValue(): void
    {
        $this->assertTrue((new Iri('http://example.com/a'))->equals(new Iri('http://example.com/a')));
    }

    public function testDoesNotEqualAnIriWithADifferentValue(): void
    {
        $this->assertFalse((new Iri('http://example.com/a'))->equals(new Iri('http://example.com/A')));
    }

    public function testDoesNotEqualAnIriThatDiffersOnlyByNormalization(): void
    {
        $this->assertFalse((new Iri('HTTP://example.com/a'))->equals(new Iri('http://example.com/a')));
        $this->assertFalse((new Iri('http://example.com/%7Ea'))->equals(new Iri('http://example.com/~a')));
    }

    public function testDoesNotEqualAnotherKindOfTerm(): void
    {
        $other = new class implements Resource {
            public function equals(Term $other): bool
            {
                return true;
            }
        };

        $this->assertFalse((new Iri('http://example.com/a'))->equals($other));
    }
}
