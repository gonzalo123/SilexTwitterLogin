<?php

use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;
use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class SilexTwitterLogin
{
    private $app;
    private $controllersFactory;
    private $onLoggin;
    private $sessionId = self::DEFAULT_SESSION_ID;
    private $oauthToken;
    private $oauthTokenSecret;
    private $userId;
    private $screenName;
    private $consumerKey;
    private $consumerSecret;
    private $prefix;

    const API_URL            = 'https://api.twitter.com/{version}';
    const API_VERSION        = '1.1';
    const API_REQUEST_TOKEN  = "/oauth/request_token";
    const API_ACCESS_TOKEN   = "/oauth/access_token";
    const API_AUTHENTICATE   = "https://api.twitter.com/oauth/authorize?";
    const DEFAULT_SESSION_ID = 'twitter';

    public function __construct(Application $app)
    {
        $this->app                = $app;
        $this->controllersFactory = $app['controllers_factory'];
        $this->defineApp();
    }

    public function mountOn($prefix, callable $loginCallaback)
    {
        $this->app->get($prefix, $loginCallaback);
        $this->prefix = $prefix;
        $this->app->mount($prefix, $this->getControllersFactory());
    }

    private function getControllersFactory()
    {
        return $this->controllersFactory;
    }

    private function defineApp()
    {
        $this->setUpRedirectMiddleware();

        $this->controllersFactory->get('/requestToken', function () {
            return $this->getRequestToken();
        });

        $this->controllersFactory->get('/callbackUrl', function () {
            return $this->getCallbackUrl();
        });
    }

    private function setUpRedirectMiddleware()
    {
        $this->app->before(
            function (Request $request) {
                $path = $request->getPathInfo();
                if (!$this->app['session']->has($this->sessionId)) {
                    if (!in_array($path, ["{$this->prefix}", "{$this->prefix}/requestToken", "{$this->prefix}/callbackUrl"])) {
                        return new RedirectResponse("{$this->prefix}");
                    }
                }
            }
        );
    }

    private function getRequestToken()
    {
        $client = $this->getClient();
        $oauth  = new OauthPlugin([
            'consumer_key'    => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
        ]);

        $client->addSubscriber($oauth);

        $response = $client->post(self::API_REQUEST_TOKEN)->send();

        $oauth_token = $oauth_token_secret = $oauth_callback_confirmed = null;
        parse_str((string)$response->getBody());

        if ($response->getStatusCode() == 200 && $oauth_callback_confirmed == 'true') {
            $redirectResponse = new RedirectResponse(self::API_AUTHENTICATE . http_build_query(['oauth_token' => $oauth_token]), 302);
            $redirectResponse->send();
        }

        return $this->app->redirect('/');
    }

    private function getCallbackUrl()
    {
        $client = $this->getClient();

        /** @var Request $request */
        $request = $this->app['request'];
        $oauth   = new OauthPlugin([
            'consumer_key' => $this->consumerKey,
            'token'        => $request->get('oauth_token'),
            'verifier'     => $request->get('oauth_verifier'),
        ]);

        $client->addSubscriber($oauth);

        $response    = $client->post(self::API_ACCESS_TOKEN)->send();
        $oauth_token = $oauth_token_secret = $user_id = $screen_name = null;

        parse_str((string)$response->getBody());
        $this->userId           = $user_id;
        $this->screenName       = $screen_name;
        $this->oauthToken       = $oauth_token;
        $this->oauthTokenSecret = $oauth_token_secret;

        if (is_callable($this->onLoggin)) {
            call_user_func($this->onLoggin);
        }

        return $this->app->redirect('/');
    }

    public function registerOnLoggin(callable $onLoggin)
    {
        $this->onLoggin = $onLoggin;
    }

    private function getClient()
    {
        return new Client(self::API_URL, ['version' => self::API_VERSION]);
    }

    public function getOauthToken()
    {
        return $this->oauthToken;
    }

    public function getOauthTokenSecret()
    {
        return $this->oauthTokenSecret;
    }

    public function getScreenName()
    {
        return $this->screenName;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function setConsumerKey($consumerKey)
    {
        $this->consumerKey = $consumerKey;
    }

    public function setConsumerSecret($consumerSecret)
    {
        $this->consumerSecret = $consumerSecret;
    }

    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }
}