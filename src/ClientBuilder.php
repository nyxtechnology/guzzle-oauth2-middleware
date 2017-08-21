<?php

namespace Softonic\OAuth2\Guzzle\Middleware;

use GuzzleHttp\HandlerStack as Stack;
use League\OAuth2\Client\Provider\AbstractProvider as OAuth2Provider;
use Psr\Cache\CacheItemPoolInterface as Cache;

class ClientBuilder
{
    public static function build(
        OAuth2Provider $oauthProvider,
        array $tokenOptions,
        Cache $cache,
        array $guzzleOptions = null
    ): \GuzzleHttp\Client {
        $cacheHandler = new AccessTokenCacheHandler($cache);

        $stack = static::getStack();

        $stack = static::addHeaderMiddlewareToStack(
            $stack,
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );
        $stack = static::addRetryMiddlewareToStack(
            $stack,
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );

        $defaultOptions = [
            'handler' => $stack,
        ];
        $guzzleOptions = static::mergeOptions($defaultOptions, $guzzleOptions);

        return new \GuzzleHttp\Client($guzzleOptions);
    }

    protected static function getStack(): Stack
    {
        $stack = new Stack();
        $stack->setHandler(new \GuzzleHttp\Handler\CurlHandler());
        return $stack;
    }

    protected static function addHeaderMiddlewareToStack(
        Stack $stack,
        OAuth2Provider $oauthProvider,
        array $tokenOptions,
        AccessTokenCacheHandler $cacheHandler
    ): Stack {
        $addAuthorizationHeader = new AddAuthorizationHeader(
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );

        $stack->push(\GuzzleHttp\Middleware::mapRequest($addAuthorizationHeader));
        return $stack;
    }

    protected static function addRetryMiddlewareToStack(
        Stack $stack,
        OAuth2Provider $oauthProvider,
        array $tokenOptions,
        AccessTokenCacheHandler $cacheHandler
    ): Stack {
        $retryOnAuthorizationError = new RetryOnAuthorizationError(
            $oauthProvider,
            $tokenOptions,
            $cacheHandler
        );

        $stack->push(\GuzzleHttp\Middleware::retry($retryOnAuthorizationError));
        return $stack;
    }

    protected static function mergeOptions(array $defaultOptions, array $options = null): array
    {
        $options = $options ?? [];
        return array_merge($options, $defaultOptions);
    }
}
