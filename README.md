<h1 align="center">socialweb/rdf</h1>

<p align="center">
    <a href="https://github.com/socialweb-php/rdf"><img src="https://img.shields.io/badge/source-socialweb/rdf-blue.svg?style=flat-square" alt="Source Code"></a>
    <a href="https://packagist.org/packages/socialweb/rdf"><img src="https://img.shields.io/packagist/v/socialweb/rdf.svg?style=flat-square&label=release" alt="Download Package"></a>
    <a href="https://php.net"><img src="https://img.shields.io/packagist/php-v/socialweb/rdf.svg?style=flat-square&colorB=%238892BF" alt="PHP Programming Language"></a>
    <a href="https://github.com/socialweb-php/rdf/blob/main/COPYING.LESSER"><img src="https://img.shields.io/packagist/l/socialweb/rdf.svg?style=flat-square&colorB=darkcyan" alt="Read License"></a>
    <a href="https://github.com/socialweb-php/rdf/actions/workflows/continuous-integration.yml"><img src="https://img.shields.io/github/actions/workflow/status/socialweb-php/rdf/continuous-integration.yml?branch=main&style=flat-square&logo=github" alt="Build Status"></a>
    <a href="https://codecov.io/gh/socialweb-php/rdf"><img src="https://img.shields.io/codecov/c/gh/socialweb-php/rdf?label=codecov&logo=codecov&style=flat-square" alt="Codecov Code Coverage"></a>
</p>

## About

socialweb/rdf is a PHP library for the [RDF 1.1][rdf11-concepts] data model.
It provides:

- a core model of terms (`Iri`, `BlankNode`, `Literal`), quads, and datasets,
- an [N-Quads 1.1][n-quads] parser and a serializer that writes the canonical
  form of N-Quads, and
- [RDF Dataset Canonicalization (RDFC-1.0)][rdf-canon], which turns any
  dataset into a stable byte sequence that can be hashed and signed.

The library implements these three specifications faithfully. It has no package
dependencies, never makes network requests, does bounded work on untrusted
input, and fails explicitly rather than repairing malformed data. Terms and
quads are immutable value objects; `Dataset` is the one mutable type.

Not included, by design: other syntaxes such as Turtle, pattern matching,
literal value conversion, IRI normalization, and the RDF 1.2 additions.

This project adheres to a [code of conduct](CODE_OF_CONDUCT.md).
By participating in this project and its community, you are expected to
uphold this code.

## Installation

Install this package as a dependency using [Composer](https://getcomposer.org).

``` bash
composer require socialweb/rdf
```

## Usage

### Canonicalizing an N-Quads document

Parse the document into a dataset, canonicalize it, and hash the canonical
N-Quads. Two documents that describe the same graph, with different blank
node labels or statement order, produce the same bytes.

``` php
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\NQuads\Parser;

$dataset = (new Parser())->parse($nquadsText);
$result = (new Canonicalizer())->canonicalize($dataset);

$canonicalNQuads = $result->toNQuads();
$hash = hash('sha256', $canonicalNQuads);
```

`Parser::parse()` raises `SocialWeb\Rdf\Exception\MalformedNQuads`, which
carries the line and column of the first problem, if the document does not
follow the N-Quads grammar.

### Canonicalizing a dataset produced by another library

Any code that can enumerate statements can build a `Dataset`. Map each
statement's components to this library's terms: `Iri` for IRIs, `BlankNode`
for blank nodes, and `Literal` for literals, with `DefaultGraph` as the graph
name of statements that belong to no named graph.

``` php
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Canonicalization\Canonicalizer;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\Quad;

$dataset = new Dataset();

foreach ($statements as $statement) {
    $dataset->add(new Quad(
        $statement->subjectIsBlank ? new BlankNode($statement->subject) : new Iri($statement->subject),
        new Iri($statement->predicate),
        $statement->objectIsLiteral
            ? new Literal($statement->object, language: $statement->language)
            : new Iri($statement->object),
    ));
}

$result = (new Canonicalizer())->canonicalize($dataset);

$result->toNQuads();           // the canonical N-Quads document
$result->dataset();            // a Dataset with canonical blank node labels
$result->issuedIdentifiers();  // each input blank node label to its canonical label
```

### Building a dataset by hand

``` php
use SocialWeb\Rdf\BlankNode;
use SocialWeb\Rdf\Dataset;
use SocialWeb\Rdf\Iri;
use SocialWeb\Rdf\Literal;
use SocialWeb\Rdf\NQuads\Serializer;
use SocialWeb\Rdf\Quad;

$alice = new BlankNode('alice');
$name = new Iri('http://xmlns.com/foaf/0.1/name');
$knows = new Iri('http://xmlns.com/foaf/0.1/knows');

$dataset = new Dataset([
    new Quad($alice, $name, new Literal('Alice', language: 'en')),
    new Quad($alice, $knows, new BlankNode('bob')),
    new Quad(new BlankNode('bob'), $name, new Literal('Bob')),
]);

echo (new Serializer())->serialize($dataset);
```

This prints:

``` text
_:alice <http://xmlns.com/foaf/0.1/name> "Alice"@en .
_:alice <http://xmlns.com/foaf/0.1/knows> _:bob .
_:bob <http://xmlns.com/foaf/0.1/name> "Bob" .
```

### Limiting the work canonicalization may do

RDFC-1.0 can take a very long time on datasets built to defeat it, so the
canonicalizer bounds the number of deep comparisons it makes and raises
`SocialWeb\Rdf\Exception\IterationLimitExceeded` when the bound is reached.
The bound is set by the constructor's work factor, following the reference
implementation: the number of blank nodes whose first-degree hashes are not
unique, raised to the power of the work factor.

``` php
use SocialWeb\Rdf\Canonicalization\Canonicalizer;

new Canonicalizer();                         // SHA-256, work factor 1
new Canonicalizer('sha384');                 // SHA-384, work factor 1
new Canonicalizer(workFactor: 2);            // allows the square of that count
new Canonicalizer(maxDeepIterations: 1000);  // an explicit limit instead
```

The default work factor of 1 handles typical data, including trees of blank
nodes and blank nodes that can be told apart by their immediate neighbors.
Datasets in which several blank nodes are only distinguishable through
longer chains of other blank nodes, such as cycles, need a work factor of 2
or more. The W3C test suite runs its medium-complexity cases with a work
factor of 2 and its high-complexity cases with 3.

## Contributing

Contributions are welcome! To contribute, please familiarize yourself with
[CONTRIBUTING.md](CONTRIBUTING.md).

## Coordinated Disclosure

Keeping user information safe and secure is a top priority, and we welcome the
contribution of external security researchers. If you believe you've found a
security issue in software that is maintained in this repository, please read
[SECURITY.md](SECURITY.md) for instructions on submitting a vulnerability report.

## Copyright and License

socialweb/rdf is copyright © socialweb/rdf Contributors and licensed for use
under the terms of the GNU Lesser General Public License (LGPL-3.0-or-later) as
published by the Free Software Foundation. Please see
[COPYING.LESSER](COPYING.LESSER), [COPYING](COPYING), and [NOTICE](NOTICE) for
more information.

[rdf11-concepts]: https://www.w3.org/TR/rdf11-concepts/
[n-quads]: https://www.w3.org/TR/n-quads/
[rdf-canon]: https://www.w3.org/TR/rdf-canon/
