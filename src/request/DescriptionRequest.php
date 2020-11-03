<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 22:20:20
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayPartsRequest;

use function array_merge;
use function base64_encode;
use function implode;
use function sha1;

/**
 * Добавление описание платежа.
 *
 * @link https://bw.gitbooks.io/api-oc/content/dobavlenie_opisaniya_platezha.html
 */
class DescriptionRequest extends PayPartsRequest
{
    /** @var string описание платежа */
    public $description;

    /**
     * @inheritDoc
     */
    public function rules() : array
    {
        return array_merge(parent::rules(), [
            ['description', 'trim'],
            ['description', 'required']
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function url() : string
    {
        return 'payment/description';
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
            $this->description,
            $this->module->password
        ]), true));
    }
}
