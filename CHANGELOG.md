# socialweb/rdf Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 - TBD

### Added

- Core RDF 1.1 model: `Iri`, `BlankNode`, `Literal`, `DefaultGraph`, `Quad`, and `Dataset`, with exact-comparison equality and constructor validation.
- Exceptions under `SocialWeb\Rdf\Exception`: the `RdfException` marker interface, `InvalidArgument`, `MalformedNQuads`, and `IterationLimitExceeded`.
- Public interfaces `Term`, `Resource`, and `GraphName`, and the vocabulary constant classes `Vocabulary\Rdf` and `Vocabulary\Xsd`.
- N-Quads 1.1 support under `SocialWeb\Rdf\NQuads`: `Parser` reads a document into quads or a `Dataset` and reports the first malformed line with its line and column, and `Serializer` writes quads in the canonical form of N-Quads defined by RDF Dataset Canonicalization (RDFC-1.0).

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
