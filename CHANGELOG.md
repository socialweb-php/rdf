# socialweb/rdf Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Dataset::fromNQuads()` parses an N-Quads document into a dataset, and `Dataset::toNQuads()` and `Quad::toNQuads()` write a dataset or a quad in the canonical form of N-Quads. They replace `NQuads\Parser` and `NQuads\Serializer` in the public surface.
- `HashAlgorithm`, an enum of the hash functions the canonicalizer accepts: `Sha256`, `Sha384`, and `Sha512`, backed by their registry names, with `hashName()` and `digestLength()`.
- `Exception\MalformedNQuadsReason`, an enum naming the rule a malformed line broke, exposed as `MalformedNQuads::$reason`.
- `Exception\LimitExceeded`, raised when canonicalization reaches the deep iteration limit, with `$parameter` naming the constructor parameter and `$limit` holding the bound in force.

### Changed

- `Canonicalizer::__construct()` takes a `HashAlgorithm` case instead of a string, so an algorithm `hash()` knows but RDFC-1.0 does not name is no longer accepted.
- An explicit `maxDeepIterations` must be at least 1; `null` still derives the limit from the work factor. A negative work factor is refused with a message that names the parameter.
- `CanonicalizationResult::$dataset` and `CanonicalizationResult::$issuedIdentifiers` are public properties in place of the `dataset()` and `issuedIdentifiers()` methods.
- `MalformedNQuads` extends `UnexpectedValueException` instead of `RuntimeException`, takes its reason first, and has a one-sentence message that quotes the offending line. The parser's messages quote the expected punctuation with double quotes.
- `NQuads\Parser` is internal and parses with one static method; `NQuads\Grammar` and `NQuads\Scanner` stay internal.

### Removed

- `NQuads\Serializer`; use `Dataset::toNQuads()` and `Quad::toNQuads()`.
- `Exception\IterationLimitExceeded`; catch `Exception\LimitExceeded`.
- `Dataset::toArray()`; call `iterator_to_array()` on the dataset.
- `CanonicalizationResult::dataset()` and `CanonicalizationResult::issuedIdentifiers()`; read the properties.

## [0.1.0] - 2026-09-15

### Added

- Core RDF 1.1 model: `Iri`, `BlankNode`, `Literal`, `DefaultGraph`, `Quad`, and `Dataset`, with exact-comparison equality and constructor validation.
- Exceptions under `SocialWeb\Rdf\Exception`: the `RdfException` marker interface, `InvalidArgument`, `MalformedNQuads`, and `IterationLimitExceeded`.
- Public interfaces `Term`, `Resource`, and `GraphName`, and the vocabulary constant classes `Vocabulary\Rdf` and `Vocabulary\Xsd`.
- N-Quads 1.1 support under `SocialWeb\Rdf\NQuads`: `Parser` reads a document into quads or a `Dataset` and reports the first malformed line with its line and column, and `Serializer` writes quads in the canonical form of N-Quads defined by RDF Dataset Canonicalization (RDFC-1.0).
- RDF Dataset Canonicalization (RDFC-1.0) under `SocialWeb\Rdf\Canonicalization`: `Canonicalizer` assigns canonical blank node identifiers to a `Dataset` and returns a `CanonicalizationResult` holding the canonicalized dataset, the canonical N-Quads document, and the map from input to canonical identifiers. The constructor takes the hash algorithm (SHA-256 by default, SHA-384 supported), a work factor that bounds deep iterations, and an optional explicit iteration limit; reaching the limit raises `IterationLimitExceeded`.

[unreleased]: https://github.com/socialweb-php/rdf/compare/0.1.0...HEAD
[0.1.0]: https://github.com/socialweb-php/rdf/compare/0.0.0...0.1.0
