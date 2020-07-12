<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 12.07.20 23:04:24
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\validate\ValidateException;
use yii\base\Exception;
use function array_filter;
use function base64_encode;
use function implode;
use function sha1;

/**
 * Подтверждение платежа, созданного с удержанием (PaymentRequest::hold).
 */
class ConfirmRequest extends AbstractRequest
{
    /** @var string заказ */
    public $orderId;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            ['orderId', 'trim'],
            ['orderId', 'required'],
            ['orderId', 'string', 'max' => 64],
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
            'signature' => $this->signature()
        ], static function($val) {
            return $val !== null && $val !== '';
        });
    }

    /**
     * Отправляет запрос
     *
     * @throws Exception
     */
    public function send()
    {
        $this->sendData('confirm', $this->json());
    }
}
