<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:19:38
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
    public function rules() : array
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
    public function attributesToJson() : array
    {
        return array_merge(parent::attributesToJson(), [
            'showRefund' => isset($this->showRefund) ? ($this->showRefund ? 'true' : 'false') : null,
            'showAmount' => isset($this->showAmount) ? ($this->showAmount ? 'true' : 'false') : null
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function url() : string
    {
        return 'payment/state';
    }
}
