<?php

/**
 * This file is part of socialweb/rdf
 *
 * Copyright © socialweb/rdf Contributors
 *
 * socialweb/rdf is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * socialweb/rdf is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with socialweb/rdf. If not, see <https://www.gnu.org/licenses/>.
 *
 * SPDX-License-Identifier: LGPL-3.0-or-later
 */

declare(strict_types=1);

namespace SocialWeb\Rdf;

/**
 * The hash functions the canonicalizer may use, backed by their names in the
 * IANA Named Information Hash Algorithm Registry
 *
 * RDFC-1.0 section 3.1 requires an implementation to use SHA-256 by default
 * and to support SHA-384. SHA-512 is accepted because every socialweb package
 * declares this enum with the same cases.
 */
enum HashAlgorithm: string
{
    case Sha256 = 'sha-256';
    case Sha384 = 'sha-384';
    case Sha512 = 'sha-512';

    /**
     * Returns the name `hash()` knows the function by
     */
    public function hashName(): string
    {
        return match ($this) {
            self::Sha256 => 'sha256',
            self::Sha384 => 'sha384',
            self::Sha512 => 'sha512',
        };
    }

    /**
     * Returns the length of a digest in bytes
     */
    public function digestLength(): int
    {
        return match ($this) {
            self::Sha256 => 32,
            self::Sha384 => 48,
            self::Sha512 => 64,
        };
    }
}
