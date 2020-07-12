<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 00:46:31
 */

declare(strict_types = 1);
namespace dicr\payparts;

use yii\base\Model;

/**
 * Ответ на запрос.
 */
class Response extends Model implements PayParts
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
            ['state', 'trim'],
            ['state', 'in', 'range' => [self::STATE_SUCCESS, self::STATE_FAIL]],

            ['paymentState', 'trim'],
            ['paymentState', 'in', 'range' => array_keys(self::STATES)],

            ['storeId', 'trim'],

            ['orderId', 'trim'],
            ['orderId', 'required'],

            ['token', 'trim'],

            ['message', 'trim'],

            ['signature', 'trim'],
            ['signature', 'required'],
        ];
    }
}
