<?php
/**
 * @copyright Copyright (c) 2024 Sagar Gurung <sagar@jankaritech.com>
 *
 * @author Sagar Gurung <sagar@jankaritech.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\OpenProject\Model;

use JsonSerializable;

class Token implements JsonSerializable {

    private string $idToken;
    private string $accessToken;
    private int $expiresIn;
    private string $refreshToken;
    private int $createdAt;
    private ?int $providerId;

    public function __construct(array $tokenData) {
        $this->idToken = $tokenData['id_token'];
        $this->accessToken = $tokenData['access_token'];
        $this->expiresIn = $tokenData['expires_in'];
        $this->refreshToken = $tokenData['refresh_token'];
        $this->createdAt = $tokenData['created_at'] ?? time();
        $this->providerId = $tokenData['provider_id'] ?? null;
    }

    public function getAccessToken(): string {
        return $this->accessToken;
    }

    public function getIdToken(): string {
        return $this->idToken;
    }

    public function getExpiresIn(): int {
        return $this->expiresIn;
    }

    public function getRefreshToken(): string {
        return $this->refreshToken;
    }

    public function getProviderId(): ?int {
        return $this->providerId;
    }

    public function isExpired(): bool {
        return time() > ($this->createdAt + $this->expiresIn);
    }

    public function isExpiring(): bool {
        return time() > ($this->createdAt + (int)($this->expiresIn / 2));
    }

    public function getCreatedAt() {
        return $this->createdAt;
    }

    public function jsonSerialize(): array {
        return [
            'id_token' => $this->idToken,
            'access_token' => $this->accessToken,
            'expires_in' => $this->expiresIn,
            'refresh_token' => $this->refreshToken,
            'created_at' => $this->createdAt,
            'provider_id' => $this->providerId,
        ];
    }
}
