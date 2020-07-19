<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 19.07.20 04:55:11
 */

declare(strict_types = 1);
namespace dicr\tests;

use dicr\payparts\Payparts;
use dicr\payparts\PaypartsModule;
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
        /** @var PaypartsModule $module */
        $module = Yii::$app->getModule('payparts');

        $request = $module->createPaymentRequest([
            'orderId' => (string)time(), // требуется постоянно новый
            'partsCount' => 2,
            'merchantType' => Payparts::MERCHANT_TYPE_PP,
            'products' => [
                ['name' => 'Многофункциональный инструмент Metabo MT 400 QUICK', 'price' => 300.12, 'count' => 3],
            ],
            // URL не работают
            'responseUrl' => '',
            'redirectUrl' => ''
        ]);

        $token = $request->send();
        self::assertNotEmpty($token);
    }
}
