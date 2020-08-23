<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 19:44:30
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayPartsRequest;

use function array_merge;
use function base64_encode;
use function implode;
use function sha1;

/**
 * Отмена совершенного платежа.
 *
 * @link https://bw.gitbooks.io/api-oc/content/decline.html
 */
class DeclineRequest extends PayPartsRequest
{
    /** @var float сума покупки */
    public $amount;

    /** @var ?string */
    public $recipientId;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['amount', 'required'],
            ['amount', 'number', 'min' => 0.01],
            ['amount', 'filter', 'filter' => 'floatval'],

            ['recipientId', 'trim'],
            ['recipientId', 'default']
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function func(): string
    {
        return 'payment/decline';
    }

    /**
     * @inheritDoc
     */
    protected function data(): array
    {
        return array_merge(parent::data(), [
            'amount' => sprintf('%.2f', $this->amount),
            'recipientId' => $this->recipientId
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function signature(): string
    {
        return base64_encode(sha1(implode('', [
            $this->_module->password,
            $this->_module->storeId,
            $this->orderId,
            (int)($this->amount * 100),
            $this->_module->password
        ]), true));
    }
}
