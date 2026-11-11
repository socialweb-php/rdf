<h1 align="center">socialweb/rdf</h1>

<p align="center">
    <a href="https://github.com/socialweb-php/rdf"><img src="https://img.shields.io/badge/source-socialweb/rdf-blue.svg" alt="Source Code"></a>
    <a href="https://packagist.org/packages/socialweb/rdf"><img src="https://img.shields.io/packagist/v/socialweb/rdf.svg?label=release" alt="Download Package"></a>
    <a href="https://php.net"><img src="https://img.shields.io/packagist/php-v/socialweb/rdf.svg?colorB=%238892BF" alt="PHP Programming Language"></a>
    <a href="https://github.com/socialweb-php/rdf/blob/main/COPYING.LESSER"><img src="https://img.shields.io/packagist/l/socialweb/rdf.svg?colorB=darkcyan&abc=1" alt="Read License"></a>
    <a href="https://github.com/socialweb-php/rdf/actions/workflows/continuous-integration.yml"><img src="https://img.shields.io/github/actions/workflow/status/socialweb-php/rdf/continuous-integration.yml?branch=main&logo=github" alt="Build Status"></a>
    <a href="https://codecov.io/gh/socialweb-php/rdf"><img src="https://img.shields.io/codecov/c/gh/socialweb-php/rdf?label=codecov&logo=codecov" alt="Codecov Code Coverage"></a>
</p>

## About

socialweb/rdf models [RDF 1.1][rdf11-concepts] data, reads and writes it as [N-Quads 1.1][n-quads], and canonicalizes it with [RDF Dataset Canonicalization (RDFC-1.0)][rdf-canon].

You have a set of statements, perhaps converted from JSON-LD or read from another store, and you need to sign it, hash it, or check that a copy someone sent you is the one you signed. Two copies of the same data rarely have the same bytes: the statements may be listed in a different order, and the blank nodes, the nodes with no name of their own, may have different labels in each copy. A signature over either copy's bytes fails for the other, although both say the same thing.

RDFC-1.0 solves this by giving every blank node a label that depends only on the shape of the data and by sorting the statements, so that any two copies of the same data produce the same bytes. This package implements that algorithm, the N-Quads syntax it reads and writes, and the data model both rest on: terms (`Iri`, `BlankNode`, `Literal`), quads, and datasets. Reading other syntaxes, such as Turtle or JSON-LD, is left to other libraries; a dataset they produce can be built from this package's terms, and a sibling package, [socialweb/json-ld][json-ld], converts JSON-LD into one. The package has no dependencies beyond PHP, never makes a network request, and bounds the work it does on data built to defeat canonicalization.

This project adheres to a [code of conduct](CODE_OF_CONDUCT.md). By participating in this project and its community, you are expected to uphold this code.

## Installation

Install this package as a dependency using [Composer](https://getcomposer.org).

```bash
composer require socialweb/rdf
```

## Usage

### Canonicalizing a dataset so that it can be hashed or signed

You have an N-Quads document and want bytes you can hash or sign, which anyone holding the same data can reproduce. `Dataset::fromNQuads()` parses the document into a `Dataset`, `Canonicalizer::canonicalize()` assigns the canonical blank node labels, and `toNQuads()` on the result writes the sorted canonical N-Quads. Hash that string.

The two documents below record the same game session. They list the statements in a different order and label their blank nodes differently, and they still canonicalize to the same bytes.

```php
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\Dataset;

$fromTheGamemaster = <<<'NQUADS'
    _:session <https://example.com/ns/game#gamemaster> "Haruto Fuwa" .
    _:session <https://example.com/ns/game#player> _:amara .
    _:session <https://example.com/ns/game#player> _:diego .
    _:amara <https://example.com/ns/game#plays> "Oriel" .
    _:diego <https://example.com/ns/game#plays> "Kaelen" .
    NQUADS;

$fromAPlayer = <<<'NQUADS'
    _:b1 <https://example.com/ns/game#plays> "Kaelen" .
    _:b0 <https://example.com/ns/game#plays> "Oriel" .
    _:s <https://example.com/ns/game#player> _:b1 .
    _:s <https://example.com/ns/game#player> _:b0 .
    _:s <https://example.com/ns/game#gamemaster> "Haruto Fuwa" .
    NQUADS;

$canonicalizer = new Canonicalizer();

$first = $canonicalizer->canonicalize(Dataset::fromNQuads($fromTheGamemaster));
$second = $canonicalizer->canonicalize(Dataset::fromNQuads($fromAPlayer));

echo $first->toNQuads();
echo hash('sha256', $first->toNQuads()), "\n";
echo $first->toNQuads() === $second->toNQuads() ? "the same bytes\n" : "different bytes\n";
```

This prints:

```text
_:c14n0 <https://example.com/ns/game#plays> "Oriel" .
_:c14n1 <https://example.com/ns/game#gamemaster> "Haruto Fuwa" .
_:c14n1 <https://example.com/ns/game#player> _:c14n0 .
_:c14n1 <https://example.com/ns/game#player> _:c14n2 .
_:c14n2 <https://example.com/ns/game#plays> "Kaelen" .
56cac92b540347848551936e814b3cdbe98fd04c25c551631430c747e997b79e
the same bytes
```

The result holds more than the text. Its `dataset` property is a `Dataset` whose blank nodes have their canonical labels, and its `issuedIdentifiers` property maps each input label to its canonical label:

```php
print_r($first->issuedIdentifiers);
```

```text
Array
(
    [amara] => c14n0
    [session] => c14n1
    [diego] => c14n2
)
```

RDFC-1.0 hashes with SHA-256 by default and requires SHA-384 to be available. Pass a `HashAlgorithm` case to choose; the canonical labels change with the algorithm, so both sides of a signature must use the same one.

```php
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\HashAlgorithm;

$canonicalizer = new Canonicalizer(HashAlgorithm::Sha384);
```

A document that does not follow the N-Quads grammar raises `SocialWeb\Rdf\Exception\MalformedNQuads` from `Dataset::fromNQuads()`. The exception's `reason` property is a `MalformedNQuadsReason` case naming the rule the line broke, and `lineNumber` and `columnNumber` say where. Nothing is skipped or repaired.

```php
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Exception\MalformedNQuads;

$unterminated = '<https://example.com/session/7> <https://example.com/ns/game#gamemaster> "Haruto Fuwa"';

try {
    Dataset::fromNQuads($unterminated);
} catch (MalformedNQuads $exception) {
    echo $exception->reason->name, ' at line ', $exception->lineNumber, ', column ', $exception->columnNumber, "\n";
    echo $exception->getMessage(), "\n";
}
```

```text
MissingTerminator at line 1, column 87
Malformed N-Quads at line 1, column 87: expected "." to end the statement; the offending line is "<https://example.com/session/7> <https://example.com/ns/game#gamemaster> "Haruto Fuwa""
```

### Building a dataset from data another library holds

Your statements may not be N-Quads text yet: they may be rows from a store, or the triples another RDF library produced. Any code that can enumerate statements can build a `Dataset`. Map each component to one of this package's terms: `Iri` for an IRI, `BlankNode` for a blank node, and `Literal` for a literal, with an optional datatype `Iri` or language tag. A `Quad` takes a subject, a predicate, an object, and a graph name; leave the graph name out for a statement in the default graph. `Dataset::toNQuads()` writes the dataset in insertion order, which is useful for a look at what was built.

```php
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;

$game = 'https://example.com/ns/game#';
$session = new Iri('https://example.com/session/7');
$roll = new BlankNode('roll');

$dataset = new Dataset([
    new Quad($session, new Iri($game . 'gamemaster'), new Literal('Haruto Fuwa')),
    new Quad($session, new Iri($game . 'roll'), $roll),
    new Quad($roll, new Iri($game . 'by'), new Literal('Priya Venkatesan')),
    new Quad($roll, new Iri($game . 'character'), new Literal('Tamsin', language: 'en')),
    new Quad($roll, new Iri($game . 'result'), new Literal('17', new Iri('http://www.w3.org/2001/XMLSchema#integer'))),
]);

echo $dataset->toNQuads();
```

This prints:

```text
<https://example.com/session/7> <https://example.com/ns/game#gamemaster> "Haruto Fuwa" .
<https://example.com/session/7> <https://example.com/ns/game#roll> _:roll .
_:roll <https://example.com/ns/game#by> "Priya Venkatesan" .
_:roll <https://example.com/ns/game#character> "Tamsin"@en .
_:roll <https://example.com/ns/game#result> "17"^^<http://www.w3.org/2001/XMLSchema#integer> .
```

A dataset is a set: adding a quad equal to one it already holds changes nothing, and `contains()` and `remove()` compare by value, not identity. A single quad writes itself the same way with `Quad::toNQuads()`.

### Limiting the work canonicalization may do

RDFC-1.0 can take a very long time on data built to defeat it, in which many blank nodes look alike and can be told apart only by exploring every way of pairing them. The canonicalizer bounds the number of deep comparisons it makes and raises `SocialWeb\Rdf\Exception\LimitExceeded` when the bound is reached, instead of running on. Following the reference implementation, the bound is derived from the data: the number of blank nodes whose first-degree hashes are not unique, raised to the power of the constructor's `workFactor`, which is 1 by default. Pass `maxDeepIterations` to set a fixed bound instead.

```php
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Exception\LimitExceeded;

$cycle = <<<'NQUADS'
    _:a <https://example.com/ns/game#next> _:b .
    _:b <https://example.com/ns/game#next> _:a .
    NQUADS;

try {
    (new Canonicalizer())->canonicalize(Dataset::fromNQuads($cycle));
} catch (LimitExceeded $exception) {
    echo $exception->getMessage(), "\n";
}

echo (new Canonicalizer(workFactor: 2))->canonicalize(Dataset::fromNQuads($cycle))->toNQuads();
```

This prints:

```text
The maxDeepIterations limit of 2 was exceeded
_:c14n0 <https://example.com/ns/game#next> _:c14n1 .
_:c14n1 <https://example.com/ns/game#next> _:c14n0 .
```

The exception's `parameter` property names the constructor parameter whose bound was reached, `maxDeepIterations`, and its `limit` property holds the bound in force, whether you set it or the work factor derived it.

The default work factor of 1 handles typical data, including trees of blank nodes and blank nodes that can be told apart by their immediate neighbors. Data in which several blank nodes are distinguishable only through longer chains of other blank nodes, such as the cycle above, needs a work factor of 2 or more. The W3C test suite runs its medium-complexity cases with a work factor of 2 and its high-complexity cases with 3. When you accept data from strangers, keep the default and treat `LimitExceeded` as a refusal, since a document that needs more work than that is more likely an attack than a record.

## Contributing

Contributions are welcome! To contribute, please familiarize yourself with [CONTRIBUTING.md](CONTRIBUTING.md).

## Coordinated vulnerability disclosure policy

Keeping user information safe and secure is a top priority, and we welcome the contribution of external security researchers. If you believe you've found a security issue in software that is maintained in this repository, please read [SECURITY.md](SECURITY.md) for instructions on submitting a vulnerability report.

## Copyright and license

socialweb/rdf is copyright © socialweb/rdf Contributors and licensed for use under the terms of the GNU Lesser General Public License (LGPL-3.0-or-later) as published by the Free Software Foundation. Please see [COPYING.LESSER](COPYING.LESSER), [COPYING](COPYING), and [NOTICE](NOTICE) for more information.

[rdf11-concepts]: https://www.w3.org/TR/rdf11-concepts/
[n-quads]: https://www.w3.org/TR/n-quads/
[rdf-canon]: https://www.w3.org/TR/rdf-canon/
[json-ld]: https://github.com/socialweb-php/json-ld
