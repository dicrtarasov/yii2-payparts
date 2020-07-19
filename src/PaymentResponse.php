<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 19.07.20 04:55:11
 */

declare(strict_types = 1);
namespace dicr\payparts;

use yii\base\Model;

/**
 * Ответ на запрос.
 */
class PaymentResponse extends Model implements Payparts
{
    /** @var string статус ответа на запрос */
    public $state;

    /** @var string статус заказа в callback */
    public $paymentState;

    /** @var string id магазина */
    public $storeId;

    /** @var string идентификатор заказа */
    public $orderId;

    /** @var string токен платежа */
    public $token;

    /** @var string текст сообщения */
    public $message;

    /** @var string сигнатура */
    public $signature;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            [['state', 'paymentState', 'storeId', 'orderId', 'token', 'message', 'signature'], 'trim'],
            ['signature', 'required']
        ];
    }
}
