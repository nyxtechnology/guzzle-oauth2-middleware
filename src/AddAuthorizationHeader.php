<?php

namespace Softonic\OAuth2\Guzzle\Middleware;

use League\OAuth2\Client\Provider\AbstractProvider as OAuth2Provider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\RequestInterface;

class AddAuthorizationHeader
{
    const CACHE_KEY_PREFIX = 'oauth2-token-';

    private $provider;
    private $cacheHandler;
    private $config;

    public function __construct(OAuth2Provider $provider, array $config, AccessTokenCacheHandler $cacheHandler)
    {
        $this->provider = $provider;
        $this->config = $config;
        $this->cacheHandler = $cacheHandler;
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        $token = $this->cacheHandler->getTokenByProvider($this->provider, $this->config);
        if (false === $token) {
            $accessToken = $this->getAccessToken();
            $token = $accessToken->getToken();
            $this->cacheHandler->saveTokenByProvider($accessToken, $this->provider, $this->config);
        }

        return $request->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function getAccessToken(): AccessToken
    {
        $grantType = $this->getGrantType();
        return $this->provider->getAccessToken($grantType, $this->config);
    }

    private function getGrantType(): string
    {
        if (empty($this->config['grant_type'])) {
            throw new \InvalidArgumentException('Config value `grant_type` needs to be specified.');
        }
        return $this->config['grant_type'];
    }
}
