<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:29:15
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayPartsRequest;

use function array_merge;
use function base64_encode;
use function implode;
use function sha1;
use function str_replace;

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
    public function rules() : array
    {
        return array_merge(parent::rules(), [
            ['amount', 'required'],
            ['amount', 'number', 'min' => 0.01],
            ['amount', 'filter', 'filter' => function ($val) : float {
                return round((float)$val, 2);
            }],

            ['recipientId', 'trim'],
            ['recipientId', 'default']
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function url() : string
    {
        return 'payment/decline';
    }

    /**
     * @inheritDoc
     */
    protected function signature() : string
    {
        return base64_encode(sha1(implode('', [
            $this->module->password,
            $this->module->storeId,
            $this->orderId,
            str_replace('.', '', sprintf('%.2f', $this->amount)),
            $this->module->password
        ]), true));
    }
}
