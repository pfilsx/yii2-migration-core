Yii2-migration-core - ядро миграций от Yii2 фреймворка
=============================

Установка
------------
1.  Добавить в `require` секцию `composer.json` своего проекта
```
"pfilsx/yii2-migration-core" : "*"
```
Конфигурирование
------------------
Стандартное конфигурирование yii2-console проекта(https://www.yiiframework.com/doc/guide/2.0/ru/concept-configurations#application-configurations)

Использование
---------------
1. https://www.yiiframework.com/doc/guide/2.0/ru/db-migrations
2. Дополнительные возможности yii2-oracle-pack(autoIncrement(), createPackage(), updatePackage(), undoPackage(), ...)
3. Функционал подготовки пакетов для различного окружения

Особенности
---------------
1. Функционал Yii2 урезан до миграций и QueryBuilder-а для Oracle. Все лишнее вырезано для уменьшения размера. Удалены лишние 
зависимости.
2. zlakomanov/yii2-oracle-pack интегрирован в стандартный класс yii\db\Connection для oci8.
3. Реализован отдельный класс миграций yii\db\oci8\Migration с интеграцией всех возможностей yii2-oracle-pack.

Подготовка пакетов
---------------------
Контроллер PreparePackagesController позволяет подготоваливать пакеты для использования в различном окружении(dev, test, prod...).

Исходный код пакетов хранится в директории packages.install внутри директории c миграциями и использует плейсхолдеры ``{имя плейсхолдера}``.
 
При подготовке пакетов все плейсхолдеры в пакетах будут заменены на соответствующие им значения из ``Yii::$app->params[$env]``.

По умолчанию используется ``'prod'`` environment. Если в ``Yii::$app->params`` не будет найден подмассив по заданному окружению,
то будет использоваться ``Yii::$app->params`` целиком.
  
Если значения для замены не будет обнаружено в параметрах, то замены произведено не будет.

Запуск подготовки пакетов:
```bash
php yii prepare-packages # для обычного запуска(prod окружение)
php yii prepare-packages --interactive=0 # для тихого запуска(prod окружение)

php yii prepare-packages --env=dev # для обычного запуска(dev окружение)
php yii prepare-packages --interactive=0 --env=dev # для тихого запуска(dev окружение)
```