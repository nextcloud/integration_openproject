<?php
/**
 * @copyright Copyright (c) 2021 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
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

namespace OCA\OpenProject\Service;

use OCA\OpenProject\AppInfo\Application;
use OCA\OpenProject\Model\Token;
use OCA\UserOIDC\Db\Provider;
use OCA\UserOIDC\Db\ProviderMapper;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class TokenService {
    private const SESSION_TOKEN_KEY = Application::APP_ID . '-user-token';
    private IClient $client;

    public function __construct(
        private ISession $session,
        private LoggerInterface $logger,
        private IUserSession $userSession,
        private IURLGenerator $urlGenerator,
        private IRequest $request,
        IClientService $clientService,
        private IAppManager $appManager,
        private ICrypto $crypto,
    ) {
        $this->client = $clientService->newClient();
    }

    /**
     * @throws \JsonException
     */
    public function storeToken(array $tokenData): Token {
        $token = new Token($tokenData);
        $this->session->set(self::SESSION_TOKEN_KEY, json_encode($token, JSON_THROW_ON_ERROR));
        $this->logger->info('Store token', ['app' => Application::APP_ID]);
        return $token;
    }

    public function getToken(bool $refresh = true): ?Token {
        $sessionData = $this->session->get(self::SESSION_TOKEN_KEY);
        if (!$sessionData) {
            return null;
        }

        $token = new Token(json_decode($sessionData, true, 512, JSON_THROW_ON_ERROR));
        if ($token->isExpired()) {
            return $token;
        }

        if ($refresh && $token->isExpiring()) {
            $token = $this->refresh($token);
        }
        return $token;
    }

    /**
     * @throws \JsonException
     */
    public function obtainDiscovery(Provider $provider): array {
        $url = $provider->getDiscoveryEndpoint();
        $this->logger->debug('Obtaining discovery endpoint: ' . $url, ['app' => Application::APP_ID]);
        $response = $this->client->get($url);
        $responseBody = $response->getBody();
        return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \JsonException
     */
    public function refresh(Token $token) {
        /** @var ProviderMapper $providerMapper */
        $providerMapper = \OC::$server->get(ProviderMapper::class);
        $oidcProvider = $providerMapper->getProvider($token->getProviderId());
        $discovery = $this->obtainDiscovery($oidcProvider);

        try {
            $clientSecret = $oidcProvider->getClientSecret();
            $userOidcVersion = $this->appManager->getAppVersion('user_oidc');
            // oidc provider secret encryption was introduced in v1.3.3
            if (version_compare($userOidcVersion, '1.3.3', '>=')) {
                // attempt to decrypt the oidc provider secret
                try {
                    $clientSecret = $this->crypto->decrypt($oidcProvider->getClientSecret());
                } catch (\Exception $e) {
                    $this->logger->error('Failed to decrypt oidc client secret', ['app' => Application::APP_ID]);
                }
            }
            $this->logger->debug('Refreshing the token: '.$discovery['token_endpoint'], ['app' => Application::APP_ID]);
            $result = $this->client->post(
                $discovery['token_endpoint'],
                [
                    'body' => [
                        'client_id' => $oidcProvider->getClientId(),
                        'client_secret' => $clientSecret,
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $token->getRefreshToken(),
                        // TODO check if we need a different scope for this
                        //'scope' => $oidcProvider->getScope(),
                    ],
                ]
            );
            $this->logger->debug('PARAMS: '.json_encode([
                    'client_id' => $oidcProvider->getClientId(),
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $token->getRefreshToken(),
                    // TODO check if we need a different scope for this
                    //'scope' => $oidcProvider->getScope(),
                ]), ['app' => Application::APP_ID]);
            $body = $result->getBody();
            $bodyArray = json_decode(trim($body), true, 512, JSON_THROW_ON_ERROR);
            $this->logger->debug('Refresh token success: "' . trim($body) . '"', ['app' => Application::APP_ID]);
            return $this->storeToken(
                array_merge(
                    $bodyArray,
                    ['provider_id' => $token->getProviderId()],
                )
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh token ', ['exception' => $e, 'app' => Application::APP_ID]);
            // Failed to refresh, return old token which will be retried or otherwise timeout if expired
            return $token;
        }
    }

    /**
     * @throws \JsonException
     */
    public function reauthenticate() {
        $token = $this->getToken(false);
        if ($token === null) {
            return;
        }
//        $redirectUri = 'https://keycloak.local/realms/opendesk/protocol/openid-connect/auth?client_id=nextcloud&response_type=code&scope=openid+email+profile&redirect_uri=https://nextcloud.local/apps/user_oidc/code';
        // Logout the user and redirect to the oidc login flow to gather a fresh token
        header('Location: ' . $redirectUri);
        $this->userSession->logout();
    }
}
