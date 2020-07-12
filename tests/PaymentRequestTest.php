<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 12.07.20 23:24:02
 */

declare(strict_types = 1);
namespace dicr\tests;

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
    public function testSend()
    {
        /** @var PayPartsModule $module */
        $module = Yii::$app->getModule('payparts');

        $request = $module->createPaymentRequest([
            'orderId' => (string)time(), // требуется постоянно новый
            'partsCount' => 2,
            'merchantType' => PayPartsModule::MERCHANT_TYPE_PP,
            'products' => [
                ['name' => 'Product', 'price' => 300.12, 'count' => 3],
            ],
            // URL не работают
            'responseUrl' => '',
            'redirectUrl' => ''
        ]);

        $token = $request->send();
        self::assertNotEmpty($token);
    }
}
