<?php
$env = require __DIR__ . '/env.php';

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'Poa Wallet',
    'name' => 'Poa Wallet',
    'language' => 'en-US', // Specifies which language the application is targeted to
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    // link vecchio schema (index.php?r=rotta: mail, notifiche salvate, app installate): stessa rotta, nessun redirect
    'on beforeRequest' => static function () {
        $request = Yii::$app->request;
        $route = $request->getQueryParam('r');
        if (is_string($route) && preg_match('~^[a-z0-9][a-z0-9/-]*$~', $route) && in_array($request->getPathInfo(), ['', 'index.php'], true)) {
            $params = $request->getQueryParams();
            unset($params['r']);
            $request->setPathInfo($route);
            $request->setQueryParams($params);
        }
    },
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'Erc20' => ['class' => 'app\components\Erc20'],
        'i18n' => [
            'translations' => [
                'app*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@app/messages',
                    'sourceLanguage' => 'en-US',
                    'fileMap' => [
                        'app' => 'app.php',
                        'app/error' => 'error.php',
                    ],
                ],
            ],
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => $env('COOKIE_VALIDATION_KEY'),
            // reverse proxy (gateway) whose X-Forwarded-For / X-Forwarded-Proto are trusted:
            // HTTPS detection and absolute links (activation, password reset, notifications). Empty = none.
            'trustedHosts' => array_fill_keys(
                array_values(array_filter(array_map('trim', explode(',', (string) $env('TRUSTED_PROXIES', ''))))),
                ['X-Forwarded-For', 'X-Forwarded-Proto']
            ),
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\Users',
            'enableAutoLogin' => true, //
            'authTimeout' => 7776000,
        ],
        'errorHandler' => [
            'class' => app\components\errors\WebErrorHandler::class,
            'errorAction' => 'site/error',
        ],
        'authClientCollection' => [
           'class' => 'yii\authclient\Collection',
           'clients' => [
               'google' => [
                   'class' => 'yii\authclient\clients\Google',
                   'clientId' => $env('GOOGLE_CLIENT_ID', null),
                   'clientSecret' => $env('GOOGLE_CLIENT_SECRET', null),
               ],
               'facebook' => [
                   'class' => 'yii\authclient\clients\Facebook',
                   // 'validateAuthState' => false, //TEMPORARY
                   'attributeNames' => ['id', 'email', 'first_name', 'last_name'],
                   'clientId' => $env('FACEBOOK_CLIENT_ID', null),
                   'clientSecret' => $env('FACEBOOK_CLIENT_SECRET', null),
                    // 'attributeParams' => [
                    //     'include_email' => 'true'
                    // ],
               ],
               'github' => [
                   'class' => 'yii\authclient\clients\GitHub',
                   'clientId' => $env('GITHUB_CLIENT_ID', null),
                   'clientSecret' => $env('GITHUB_CLIENT_SECRET', null),
               ],
               // 'twitter' => [
               //      'class' => 'yii\authclient\clients\Twitter',
               //      'attributeParams' => [
               //          'include_email' => 'true'
               //      ],
               //      'consumerKey' => $env('TWITTER_CONSUMER_KEY', null),
               //      'consumerSecret' => $env('TWITTER_CONSUMER_SECRET', null),
               //  ],
               // etc.
           ],
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => filter_var($env('MAIL_USE_FILE_TRANSPORT', 'false'), FILTER_VALIDATE_BOOLEAN),
            'transport' => [
                'dsn' => sprintf(
                    '%s://%s:%s@%s:%s',
                    $env('MAIL_ENCRYPTION', 'tls') === 'ssl' ? 'smtps' : 'smtp',
                    urlencode($env('MAIL_USERNAME')),
                    urlencode($env('MAIL_PASSWORD')),
                    $env('MAIL_HOST'),
                    $env('MAIL_PORT')
                ),
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,

        //this component manage the url creation
        // URL puliti (/wallet/index); Apache instrada su index.php con FallbackResource (config/apache.conf)
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],

        // this component add timestamp to downloaded css or javascript
        // to avoid forced refresh during develop
        //
        // ?v=<mtime> sugli asset: il service worker mette in cache per URL, così le modifiche arrivano ai browser
        'assetManager' => [
            'appendTimestamp' => true,
            // Tabler locale al posto di Bootstrap 5 (CSS e JS), come nel backoffice Napay
            'bundles' => [
                'yii\bootstrap5\BootstrapAsset' => ['class' => app\assets\TablerAsset::class],
                'yii\bootstrap5\BootstrapPluginAsset' => ['class' => app\assets\TablerPluginAsset::class],
            ],
        ],

    ],
    'params' => $params,
    // paginazione Bootstrap 5 (Tabler) in tutti gli elenchi GridView/ListView
    'container' => [
        'definitions' => [
            \yii\widgets\LinkPager::class => \yii\bootstrap5\LinkPager::class,
        ],
    ],
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

$redis = require __DIR__ . '/redis.php';
if ($redis !== null) {
    $config['components']['redis'] = $redis;
}

return $config;
