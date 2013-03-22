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
    private $redirectOnSuccess = self::DEFAULT_REDIRECT_ON_SUCCESS;
    private $requestTokenRoute = self::DEFAULT_REQUESTTOKEN;
    private $callbackUrlRoute = self::DEFAULT_CALLBACKURL;
    private $routesWithoutLogin;

    const API_URL                     = 'https://api.twitter.com/{version}';
    const API_VERSION                 = '1.1';
    const API_REQUEST_TOKEN           = "/oauth/request_token";
    const API_ACCESS_TOKEN            = "/oauth/access_token";
    const API_AUTHENTICATE            = "https://api.twitter.com/oauth/authorize?";
    const DEFAULT_SESSION_ID          = 'twitter';
    const DEFAULT_REQUESTTOKEN        = 'requestToken';
    const DEFAULT_CALLBACKURL         = 'callbackUrl';
    const DEFAULT_REDIRECT_ON_SUCCESS = '/';

    public function __construct(Application $app)
    {
        $this->app                = $app;
        $this->controllersFactory = $app['controllers_factory'];
    }

    public function mountOn($prefix, $loginCallaback)
    {
        $this->prefix = $prefix;
        $this->app->get($prefix, $loginCallaback);
        $this->defineApp();
        $this->app->mount($prefix, $this->getControllersFactory());
    }

    private function getControllersFactory()
    {
        return $this->controllersFactory;
    }

    private function defineApp()
    {
        $this->setUpRedirectMiddleware();

        // ugly things due to php5.3 compatibility
        $app               = $this->app;
        $consumerKey       = $this->consumerKey;
        $consumerSecret    = $this->consumerSecret;
        $redirectOnSuccess = $this->redirectOnSuccess;
        $apiRequestToken   = self::API_REQUEST_TOKEN;
        $apiAuthenticate   = self::API_AUTHENTICATE;
        $apiAccessToken    = self::API_ACCESS_TOKEN;
        $that              = $this;
        $client            = $this->getClient();
        ////

        $this->controllersFactory->get('/' . $this->requestTokenRoute, function () use ($app, $client, $consumerKey, $consumerSecret, $apiRequestToken, $apiAuthenticate){

            $oauth  = new OauthPlugin(array(
                'consumer_key'    => $consumerKey,
                'consumer_secret' => $consumerSecret,
            ));

            $client->addSubscriber($oauth);

            $response = $client->post($apiRequestToken)->send();

            $oauth_token = $oauth_token_secret = $oauth_callback_confirmed = null;
            parse_str((string)$response->getBody());

            if ($response->getStatusCode() == 200 && $oauth_callback_confirmed == 'true') {
                $redirectResponse = new RedirectResponse($apiAuthenticate. http_build_query(array('oauth_token' => $oauth_token)), 302);
                $redirectResponse->send();
            }

            return $app->redirect('/');
        });

        $this->controllersFactory->get('/' . $this->callbackUrlRoute, function () use ($app, $client, $that, $consumerKey, $apiAccessToken, $redirectOnSuccess){

            /** @var Request $request */
            $request = $app['request'];
            $oauth   = new OauthPlugin(array(
                'consumer_key' => $consumerKey,
                'token'        => $request->get('oauth_token'),
                'verifier'     => $request->get('oauth_verifier'),
            ));

            $client->addSubscriber($oauth);

            $response    = $client->post($apiAccessToken)->send();
            $oauth_token = $oauth_token_secret = $user_id = $screen_name = null;

            parse_str((string)$response->getBody());
            $that->setUserId($user_id);
            $that->setScreenName($screen_name);
            $that->setOauthToken($oauth_token);
            $that->setOauthTokenSecret($oauth_token_secret);
            $that->triggerOnLoggin();

            return $app->redirect($redirectOnSuccess);
        });
    }

    public function triggerOnLoggin()
    {
        if (is_callable($this->onLoggin)) {
            call_user_func($this->onLoggin);
        }
    }

    private function setUpRedirectMiddleware()
    {
        // ugly things due to php5.3 compatibility
        $app                = $this->app;
        $sessionId          = $this->sessionId;
        $prefix             = $this->prefix;
        $requestTokenRoute  = $this->requestTokenRoute;
        $callbackUrlRoute   = $this->callbackUrlRoute;
        $routesWithoutLogin = $this->routesWithoutLogin;
        ////

        $this->app->before(function (Request $request) use ($app, $sessionId, $prefix, $requestTokenRoute, $callbackUrlRoute, $routesWithoutLogin) {
            $path = $request->getPathInfo();
            if (!$app['session']->has($sessionId)) {
                $withoutLogin = array($prefix, "{$prefix}/{$requestTokenRoute}", "{$prefix}/{$callbackUrlRoute}");
                foreach ($routesWithoutLogin as $route) {
                    $withoutLogin[] = $route;
                }

                if (!in_array($path, $withoutLogin)) {

                    return new RedirectResponse($prefix);
                }
            }
        });
    }

    public function registerOnLoggin($onLoggin)
    {
        $this->onLoggin = $onLoggin;
    }

    private function getClient()
    {
        return new Client(self::API_URL, array('version' => self::API_VERSION));
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

    public function setRequestTokenRoute($requestTokenRoute)
    {
        $this->requestTokenRoute = $requestTokenRoute;
    }

    public function setCallbackUrlRoute($callbackUrlRoute)
    {
        $this->callbackUrlRoute = $callbackUrlRoute;
    }

    public function setRedirectOnSuccess($redirectOnSuccess)
    {
        $this->redirectOnSuccess = $redirectOnSuccess;
    }

    public function setRoutesWithoutLogin(array $routesWithoutLogin)
    {
        $this->routesWithoutLogin = $routesWithoutLogin;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function setScreenName($screenName)
    {
        $this->screenName = $screenName;
    }

    public function setOauthToken($oauthToken)
    {
        $this->oauthToken = $oauthToken;
    }

    public function setOauthTokenSecret($oauthTokenSecret)
    {
        $this->oauthTokenSecret = $oauthTokenSecret;
    }
}