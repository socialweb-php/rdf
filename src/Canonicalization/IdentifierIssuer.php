<?php

/**
 * This file is part of socialweb/rdf
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

namespace SocialWeb\Rdf\Canonicalization;

/**
 * Issues blank node identifiers from a prefix and a counter, remembering
 * which existing identifier each one replaced
 *
 * See RDFC-1.0 sections 4.3 and 4.5. The canonicalizer keeps one issuer with
 * the prefix `c14n` for canonical identifiers and creates temporary issuers
 * with the prefix `b` while it hashes N-degree quads.
 *
 * @internal
 */
final class IdentifierIssuer
{
    /**
     * @var array<string, string> existing identifier to issued identifier, in
     *     the order they were issued
     */
    private array $issued = [];

    private int $counter = 0;

    public function __construct(private readonly string $prefix)
    {
    }

    /**
     * Returns the identifier issued for an existing identifier, issuing a new
     * one if none has been issued yet
     */
    public function issue(string $existing): string
    {
        if (isset($this->issued[$existing])) {
            return $this->issued[$existing];
        }

        $identifier = $this->prefix . $this->counter;
        $this->issued[$existing] = $identifier;
        $this->counter++;

        return $identifier;
    }

    public function hasIssued(string $existing): bool
    {
        return isset($this->issued[$existing]);
    }

    /**
     * Returns every existing identifier and the identifier issued for it, in
     * the order they were issued
     *
     * PHP stores an array key such as "0" as the integer 0, so a caller that
     * needs the keys as identifiers must cast them back to strings.
     *
     * @return array<string, string>
     */
    public function issued(): array
    {
        return $this->issued;
    }

    /**
     * Returns an independent copy that starts from the same issued map and
     * counter
     */
    public function copy(): self
    {
        return clone $this;
    }
}
