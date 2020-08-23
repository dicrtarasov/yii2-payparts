# Клиент API ПриватБанк "Оплата частями"

Документация:
- основной API: https://api.privatbank.ua/#p24/partPaymentApi
- расширенный API: https://bw.gitbooks.io/api-oc/content/
- личный кабинет https://payparts2.privatbank.ua
- тестовые данные: https://bw.gitbooks.io/api-oc/content/testdata.html

Клиент реализован в виде модуля, для обработки callback-запросов от банка.

### Конфигурация
В конфиге приложения настраиваем модуль оплаты частями. Основные настройки - это `storeId` и `password`.

```php
[
    'modules' => [
        'payparts' => [
            'class' => dicr\payparts\PayPartsModule::class,
            'storeId' => '* мой storeId *',
            'password' => '* мой password *',
            // обработчик состояний платежей (опционально)
            'callbackHandler' => static function(dicr\payparts\PayPartsResponse $response) {
                Order::setPayed($response->orderId);
            }
        ]
    ]
];
```

Также можно передать функцию в параметре `callnbackHandle` для обработки запросов от банка со статусами платежей.

### Пример создания платежа:

```php
/** @var PayPartsModule $module получаем модуль оплат */
$module = Yii::$app->getModule('payparts');

// запрос на создание платежа
$request = $module->createPaymentRequest([
    'orderId' => $orderId,                        // номер заказа в интернет-магазине
    'merchantType' => PayParts::MERCHANT_TYPE_PP, // сервис "оплата частями"
    'partsCount' => 2,                            // кол-во частей
    'products' => [                               // список товаров
        new Product(['name' => 'Рулон бумаги', 'price' => 0.01, 'count' => 2]),
        new Product(['name' => 'Автомобиль', 'price' => 123, 'count' => 1]),
        new Product(['name' => 'Талоны на Интернет', 'price' => 123.123, 'count' => 3]),
    ]
]);

// отправляем запрос и получаем токен
$response = $request->send();

echo 'Token: ' . $response->token . "\n";
echo 'Redirect URL: ' . $response->paymentUrl . "\n";

// переадресация покупателя на страницу оплаты
$response->redirectCheckout();
```

Если не установлен обработчик callback-оповещений банка, то состояние платежа можно получить дополнительным запросом:
```php
// запрос состояния платежа
$request = $module->createStateRequest([
   'orderId' => $orderId     // номер заказа 
]);

// проверяем состояние платежа
$response = $request->send();
echo 'PaymentState: ' . $response->paymentState . "\n";
```

Рабочий пример вызова реализован в тестах (директория tests).
