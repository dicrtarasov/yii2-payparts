<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 17:56:29
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayPartsRequest;

use function array_merge;

/**
 * Получение статуса платежа.
 *
 * @link https://bw.gitbooks.io/api-oc/content/state.html
 */
class StateRequest extends PayPartsRequest
{
    /** @var ?bool получить сумму сделки */
    public $showAmount;

    /** @var ?bool получить также детали о возвратах по платежу */
    public $showRefund;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['showRefund', 'showAmount'], 'default'],
            [['showRefund', 'showAmount'], 'boolean'],
            [['showRefund', 'showAmount'], 'filter', 'filter' => 'boolval', 'skipOnEmpty' => true]
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function func(): string
    {
        return 'payment/state';
    }

    /**
     * @inheritDoc
     */
    protected function data(): array
    {
        return array_merge(parent::data(), [
            'showRefund' => isset($this->showRefund) ? ($this->showRefund ? 'true' : 'false') : null,
            'showAmount' => isset($this->showAmount) ? ($this->showAmount ? 'true' : 'false') : null
        ]);
    }
}
