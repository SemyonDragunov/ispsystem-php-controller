# PHP Controller для API ISPsystem 5

Необходимо PHP >= 5.4

Классы:
* IspRequest | Отправка запросов к сервису.
* IspOperations (trait) | Подготовка данных к запросу для действий над сущностью.
* IspBillAccount | Работа с аккаунтами в BILLmanager.
* IspBillProfile | Работа с профилями плательщиков в BILLmanager.
* IspAuth | Операции с авторизацией.

## Подготовка

Пространства имен в контролере и в ApiArray не заданы, выставляйте какие нужно. ApiArray подключен через require_once в controller.php

В файле **controller.php** в классе **IspRequest** установите значения для:

* protected $path = '';
* private $_admin_login = '';
* private $_admin_pass = '';

## Как использовать

```php
$query = new IspRequest();

$query
          ->request($param, 'vhost.order') // Параметры запроса и функция.
          ->su('vasya') // Необязательно. Делаем так, чтобы запрос считался от логина vasya.
          ->auth('admin') // Необзательно. Делаем запрос от имени админа, так как у него есть права.
          ->send(); // отправляем.
```

По аналогии остальное. Все классы наследются от класса IspRequest.
Подробная информация о методах и классах в файле **controller.php**.
