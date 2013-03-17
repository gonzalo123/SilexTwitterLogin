<?php

use Symfony\Component\HttpFoundation\Request;

include __DIR__ . "/../vendor/autoload.php";

$app = new Silex\Application();
$app->register(new Silex\Provider\SessionServiceProvider());

list($consumerKey, $consumerSecret) = include __DIR__ . '/twitterCredentials.conf.php';

$twitterLoggin = new SilexTwitterLogin($app, 'twitter');
$twitterLoggin->setConsumerKey($consumerKey);
$twitterLoggin->setConsumerSecret($consumerSecret);
$twitterLoggin->registerOnLoggin(function () use ($app, $twitterLoggin) {
        $app['session']->set($twitterLoggin->getSessionId(), [
            'user_id'            => $twitterLoggin->getUserId(),
            'screen_name'        => $twitterLoggin->getScreenName(),
            'oauth_token'        => $twitterLoggin->getOauthToken(),
            'oauth_token_secret' => $twitterLoggin->getOauthTokenSecret()
            ]);
    });
$twitterLoggin->mountOn('/login', function () {
    return '<a href="/login/requestToken">login</a>';
});

$app->get('/', function () {
    return 'Hello';
});

$app->run();