<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:35:59
 */

declare(strict_types = 1);
namespace dicr\tests;

use dicr\payparts\PayParts;
use dicr\payparts\PayPartsModule;
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
    public function testSend() : void
    {
        /** @var PayPartsModule $module получаем модуль оплат */
        $module = Yii::$app->getModule('payparts');

        // придумываем номер заказа магазина (каждый раз должен быть новый)
        $orderId = (string)time();

        // создание платежа
        $response = ($module->paymentRequest([
            'orderId' => $orderId,
            'partsCount' => 2,
            'merchantType' => PayParts::MERCHANT_TYPE_PP,
            'products' => [
                ['name' => 'Рулон бумаги', 'price' => 0.01, 'count' => 2],
                ['name' => 'Автомобиль', 'price' => 123, 'count' => 1],
                ['name' => 'Талоны на Интернет', 'price' => 123.123, 'count' => 3]
            ]
        ]))->send();

        self::assertNotEmpty($response->token);
        echo 'Token: ' . $response->token . "\n";

        // проверяем генерацию URL для переадресации на страницу платежей
        self::assertNotEmpty($response->paymentUrl);
        echo 'Redirect URL: ' . $response->paymentUrl . "\n";

        // проверяем состояние платежа
        $response = ($module->stateRequest([
            'orderId' => $orderId
        ]))->send();

        self::assertContains($response->paymentState, [
            PayParts::STATE_CLIENT_WAIT, PayParts::STATE_CREATED
        ]);

        echo 'PaymentState: ' . $response->paymentState . "\n";
    }
}
