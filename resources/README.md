# Copied test suites

The directories here hold test suites copied from other projects. Each is kept byte for byte as its project published it, with that project's license file beside it. They are not part of the library, and they are left out of distribution archives.

| Directory | Project | Source | License |
| --- | --- | --- | --- |
| `n-quads/` | The World Wide Web Consortium (W3C) test suite for RDF 1.1 N-Quads | <https://github.com/w3c/rdf-tests> | [W3C Test Suite License and W3C 3-clause BSD License](https://www.w3.org/copyright/test-suites-licenses/), in `n-quads/LICENSE.md` |
| `rdf-canon/` | The W3C test suite for RDF Dataset Canonicalization | <https://github.com/w3c/rdf-canon> | [W3C Test Suite License and W3C 3-clause BSD License](https://www.w3.org/copyright/test-suites-licenses/), in `rdf-canon/LICENCE.md` |

[`manifest.json`](manifest.json) records the rest for each set: the commit the files were taken from, the date they were retrieved, which directory of that repository they came from, the copyright and license, and one SHA-256 checksum for the whole set.

The W3C Test Suite License allows the suites to be used to claim conformance only as the W3C published them. A changed copy may not be used for that, so the files here are never edited, reformatted, or given a final newline they do not have. `.gitattributes` and `.editorconfig` tell git and editors to leave their bytes alone.

## Checking the suites

Run this from the root of the repository:

```bash
composer check-fixtures
```

It computes the checksum of each set again and compares it with the one in the manifest. A job in the continuous integration workflow runs the same check on every pull request and push to the main branch.

A checksum that differs from the one in the manifest means that a file has changed since it was copied. If this happens, restore the files from the commit listed in the manifest. Do not record a new checksum unless the suite is being updated to a newer commit on purpose.

## How the checksums are made

Each checksum is one SHA-256 value that covers every file in a set's directory. It changes if the content of any file changes, if a file is renamed, or if a file is added or removed.

It is made in four steps:

1. List every file in the set's directory, by its path from that directory, with `./` in front.
2. Sort the paths byte by byte (`LC_ALL=C`), so that the order is the same on every system.
3. Compute the SHA-256 of each file, which gives one line for each file: the value, two spaces, and the path.
4. Compute the SHA-256 of those lines. That value is the checksum.

`bin/check-fixtures.php` does this in PHP. The same checksum can be made without it:

```bash
(cd resources/n-quads && find . -type f | LC_ALL=C sort | xargs shasum -a 256 | shasum -a 256)
```

The command prints the checksum, followed by two spaces and a hyphen.
