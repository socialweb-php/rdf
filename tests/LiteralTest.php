<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use PHPUnit\Framework\Attributes\DataProvider;
use SocialWeb\Rdf\Exception\InvalidArgument;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Vocabulary\Rdf;
use SocialWeb\Rdf\Vocabulary\Xsd;

class LiteralTest extends TestCase
{
    public function testDefaultsToXsdString(): void
    {
        $literal = new Literal('hello');

        $this->assertSame('hello', $literal->lexicalForm);
        $this->assertSame(Xsd::STRING, $literal->datatype->value);
        $this->assertNull($literal->language);
    }

    public function testKeepsAnExplicitDatatype(): void
    {
        $integer = new Iri('http://www.w3.org/2001/XMLSchema#integer');
        $literal = new Literal('42', $integer);

        $this->assertSame('42', $literal->lexicalForm);
        $this->assertTrue($literal->datatype->equals($integer));
        $this->assertNull($literal->language);
    }

    public function testDoesNotNormalizeTheLexicalForm(): void
    {
        $integer = new Iri('http://www.w3.org/2001/XMLSchema#integer');

        $this->assertSame('042', (new Literal('042', $integer))->lexicalForm);
    }

    public function testALanguageTagImpliesRdfLangString(): void
    {
        $literal = new Literal('hello', language: 'en');

        $this->assertSame(Rdf::LANG_STRING, $literal->datatype->value);
        $this->assertSame('en', $literal->language);
    }

    public function testAcceptsRdfLangStringWithALanguageTag(): void
    {
        $literal = new Literal('hello', new Iri(Rdf::LANG_STRING), 'en');

        $this->assertSame(Rdf::LANG_STRING, $literal->datatype->value);
        $this->assertSame('en', $literal->language);
    }

    public function testLowercasesTheLanguageTag(): void
    {
        $this->assertSame('en-us', (new Literal('hello', language: 'en-US'))->language);
        $this->assertSame('zh-hant-tw', (new Literal('hello', language: 'zh-Hant-TW'))->language);
    }

    public function testRejectsALanguageTagWithAnotherDatatype(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('must have the datatype rdf:langString');

        new Literal('hello', new Iri(Xsd::STRING), 'en');
    }

    public function testRejectsRdfLangStringWithoutALanguageTag(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('must have a language tag');

        new Literal('hello', new Iri(Rdf::LANG_STRING));
    }

    #[DataProvider('malformedLanguageTags')]
    public function testRejectsMalformedLanguageTags(string $tag): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('is not a well-formed language tag');

        new Literal('hello', language: $tag);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedLanguageTags(): iterable
    {
        yield 'empty' => [''];
        yield 'underscore' => ['en_US'];
        yield 'leading digit' => ['1en'];
        yield 'trailing hyphen' => ['en-'];
    }

    public function testRejectsALexicalFormThatIsNotUtf8(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('must be valid UTF-8');

        new Literal("\xff");
    }

    public function testAcceptsAnEmptyLexicalForm(): void
    {
        $this->assertSame('', (new Literal(''))->lexicalForm);
    }

    public function testEqualsALiteralWithTheSameComponents(): void
    {
        $this->assertTrue((new Literal('a'))->equals(new Literal('a')));
        $this->assertTrue((new Literal('a'))->equals(new Literal('a', new Iri(Xsd::STRING))));
        $this->assertTrue((new Literal('a', language: 'en'))->equals(new Literal('a', language: 'EN')));
    }

    public function testDoesNotEqualALiteralWithADifferentLexicalForm(): void
    {
        $this->assertFalse((new Literal('a'))->equals(new Literal('b')));
    }

    public function testDoesNotEqualALiteralWithADifferentDatatype(): void
    {
        $integer = new Iri('http://www.w3.org/2001/XMLSchema#integer');

        $this->assertFalse((new Literal('1'))->equals(new Literal('1', $integer)));
    }

    public function testDoesNotEqualALiteralWithADifferentLanguage(): void
    {
        $this->assertFalse((new Literal('a', language: 'en'))->equals(new Literal('a', language: 'fr')));
        $this->assertFalse((new Literal('a', language: 'en'))->equals(new Literal('a')));
    }

    public function testDoesNotEqualAnIriWithTheSameString(): void
    {
        $this->assertFalse((new Literal('a:b'))->equals(new Iri('a:b')));
    }
}
