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

namespace OCA\OpenProject\Listener;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Service\TokenService;
use OCA\UserOIDC\Event\TokenObtainedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<Event>
 */
class TokenObtainedEventListener implements IEventListener {

    public function __construct(
        private LoggerInterface $logger,
        private TokenService $tokenService,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function handle(Event $event): void {
        $this->logger->debug('handling TokenObtainedEvent', ['app' => Application::APP_ID]);
        if (!$event instanceof TokenObtainedEvent) {
            return;
        }

        $token = $event->getToken();
        $provider = $event->getProvider();

        $tokenData = $token;
        $this->logger->debug('Storing the token: ' . json_encode($tokenData), ['app' => Application::APP_ID]);
        $this->tokenService->storeToken(array_merge($tokenData, ['provider_id' => $provider->getId()]));
    }
}
