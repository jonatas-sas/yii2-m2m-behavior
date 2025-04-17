<?php

use yii\base\InvalidConfigException;
use yii\console\Application;
use yii\test\FixtureTrait;

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = [
    'id'         => 'test-app',
    'basePath'   => dirname(__DIR__),
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn'   => 'sqlite::memory:',
        ],
    ],
];

try {
    new Application($config);
} catch (InvalidConfigException $e) {
    echo 'Failed to create test application: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
