<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 20:07:17
 */

declare(strict_types = 1);
namespace dicr\tests;

use dicr\payparts\PayParts;
use dicr\payparts\PayPartsModule;
use dicr\payparts\Product;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\base\Exception;

/**
 * Class PaymentRequestTest
 */
class PaymentRequestTest extends TestCase
{
    /**
     * Тест создания платежа.
     *
     * @throws Exception
     */
    public function testSend()
    {
        /** @var PayPartsModule $module получаем модуль оплат */
        $module = Yii::$app->getModule('payparts');

        // придумываем номер заказа магазина (каждый раз должен быть новый)
        $orderId = (string)time();

        // создание платежа
        $response = ($module->createPaymentRequest([
            'orderId' => $orderId,
            'partsCount' => 2,
            'merchantType' => PayParts::MERCHANT_TYPE_PP,
            'products' => [
                new Product(['name' => 'Рулон бумаги', 'price' => 0.01, 'count' => 2]),
                new Product(['name' => 'Автомобиль', 'price' => 123, 'count' => 1]),
                new Product(['name' => 'Талоны на Интернет', 'price' => 123.123, 'count' => 3]),
            ]
        ]))->send();

        self::assertNotEmpty($response->token);
        echo 'Token: ' . $response->token . "\n";

        // проверяем генерацию URL для переадресации на страницу платежей
        self::assertNotEmpty($response->paymentUrl);
        echo 'Redirect URL: ' . $response->paymentUrl . "\n";

        // проверяем состояние платежа
        $response = ($module->createStateRequest([
            'orderId' => $orderId
        ]))->send();

        self::assertContains($response->paymentState, [
            PayParts::STATE_CLIENT_WAIT, PayParts::STATE_CREATED
        ]);

        echo 'PaymentState: ' . $response->paymentState . "\n";
    }
}
