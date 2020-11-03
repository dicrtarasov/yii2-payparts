<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:26:23
 */

/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types = 1);

/** string */
define('YII_ENV', 'dev');

/** bool */
define('YII_DEBUG', true);

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php');

new yii\console\Application([
    'id' => 'test',
    'basePath' => dirname(__DIR__),
    'components' => [
        'cache' => [
            'class' => yii\caching\FileCache::class
        ],
        'log' => [
            'targets' => [
                'file' => [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning', 'info', 'trace']
                ]
            ]
        ],
        'urlManager' => [
            'hostInfo' => 'https://dicr.org'
        ]
    ],
    'modules' => [
        'payparts' => [
            'class' => dicr\payparts\PayPartsModule::class,
            'storeId' => dicr\payparts\PayParts::TEST_STORE_ID,
            'password' => dicr\payparts\PayParts::TEST_PASSWORD,
        ]
    ],
    'bootstrap' => ['log']
]);
