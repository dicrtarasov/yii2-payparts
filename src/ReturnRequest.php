<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 00:46:43
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\validate\ValidateException;
use yii\base\Exception;
use function array_filter;
use function base64_encode;
use function implode;
use function sha1;
use function sprintf;

/**
 * Запрос возврата платежа.
 */
class ReturnRequest extends AbstractRequest
{
    /** @var string номер заказа */
    public $orderId;

    /** @var float сума покупки */
    public $amount;

    /** @var string|null */
    public $recipientId;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            ['orderId', 'trim'],
            ['orderId', 'required'],
            ['orderId', 'string', 'max' => 64],

            ['amount', 'required'],
            ['amount', 'number', 'min' => self::AMOUNT_MIN, 'max' => self::AMOUNT_MAX],
            ['amount', 'filter', 'filter' => static function($amount) {
                return (float)sprintf('%.2f', $amount);
            }],

            ['recipientId', 'trim'],
            ['recipientId', 'default']
        ];
    }

    /**
     * Сигнатура данных.
     *
     * @return string
     */
    protected function signature()
    {
        return base64_encode(sha1(implode('', [
            $this->module->password,
            $this->module->storeId,
            $this->orderId,
            (int)($this->amount * 100),
            $this->module->password
        ]), true));
    }

    /**
     * Возвращает данные для JSON.
     *
     * @return array
     * @throws ValidateException
     */
    public function json()
    {
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        return array_filter([
            'storeId' => $this->module->storeId,
            'orderId' => $this->orderId,
            'amount' => $this->amount,
            'recipientId' => $this->recipientId,
            'signature' => $this->signature()
        ], static function($val) {
            return $val !== null && $val !== '';
        });
    }

    /**
     * Отправляет запрос на отмену платежа.
     *
     * @throws Exception
     */
    public function send()
    {
        $this->sendData('decline', $this->json());
    }
}
