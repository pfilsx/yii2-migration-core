<?php
require('config.php');
$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__FILE__).'/..',
    'bootstrap' => [],
    'modules' => [],
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'oci8:dbname='.ORACLE_BASE,
            'username' => ORACLE_USER,
            'password' => ORACLE_PSWD,
            'charset' => ORACLE_ENCODING,
            'attributes' => [
                PDO::ATTR_STRINGIFY_FETCHES => true
            ]
        ]
    ]
];
return $config;