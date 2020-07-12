<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 00:48:20
 */

declare(strict_types = 1);

define('YII_ENV', 'dev');
define('YII_DEBUG', true);

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php');

/** @noinspection PhpUnhandledExceptionInspection */
new yii\console\Application([
    'id' => 'test',
    'basePath' => __DIR__,
    'components' => [
        'cache' => yii\caching\ArrayCache::class,
    ],
    'modules' => [
        'payparts' => [
            'class' => dicr\payparts\PayPartsModule::class,
            'storeId' => dicr\payparts\PayParts::TEST_STORE_ID,
            'password' => dicr\payparts\PayParts::TEST_PASSWORD,
        ]
    ]
]);
