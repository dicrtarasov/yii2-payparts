<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 17:55:54
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayPartsRequest;

/**
 * Подтверждение платежа, созданного с удержанием (PaymentRequest::hold).
 *
 * @link https://bw.gitbooks.io/api-oc/content/confirm.html
 */
class ConfirmRequest extends PayPartsRequest
{
    /**
     * @inheritDoc
     */
    protected function func(): string
    {
        return 'payment/confirm';
    }
}
