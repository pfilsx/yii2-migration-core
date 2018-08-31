Yii2-migration-core - ядро миграций от Yii2 фреймворка
=============================

Установка
------------
1.  Добавить в `require` секцию `composer.json` своего проекта
```
"filimonov/yii2-migration-core" : "*"
```
2. Добавить в `repositories` секцию `composer.json` своего проекта
```
{
    "type": "git",
    "url": "http://gitlab.newcontact.su/p.filimonov/yii2-migration-core.git"
}
```
3. Выставить настройку https в false
```
"config": {
    "secure-http": false
}
```

Использование
---------------
https://www.yiiframework.com/doc/guide/2.0/ru/db-migrations

Особенности
---------------
1. Функционал Yii2 урезан до миграций и QueryBuilder-а для Oracle. Все лишнее вырезано для уменьшения размера. Удалены лишние 
зависимости.
2. zlakomanov/yii2-oracle-pack интегрирован в стандартный класс yii\db\Connection для oci8.
3. Реализован отдельный класс миграций yii\db\oci8\Migration с интеграцией всех возможностей yii2-oracle-pack.

