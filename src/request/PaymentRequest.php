<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 20:02:14
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayPartsRequest;
use dicr\payparts\PayPartsResponse;
use dicr\payparts\Product;
use dicr\validate\ValidateException;
use yii\base\Exception;
use yii\helpers\Json;

use function array_keys;
use function array_map;
use function array_reduce;
use function base64_encode;
use function implode;
use function is_array;
use function sha1;
use function sprintf;
use function str_replace;

/**
 * Запрос создания платежа.
 *
 * Если hold = false, то создается запрос обычного платежа:
 *
 * @link https://bw.gitbooks.io/api-oc/content/Create.html
 *
 * Если hold = true, то создается запрос платежа с удержанием (без списания):
 * @link https://bw.gitbooks.io/api-oc/content/prepurchase.html
 *
 * Платеж с удержанием требует дополнительного подтверждения для списания (ConfirmRequest).
 *
 * В ответ получаем token платежа, используя который, переадресовываем клиента
 * на страницу оплаты PayPartsModule::checkoutUrl
 * @link https://bw.gitbooks.io/api-oc/content/redirect.html
 *
 * @property-read float $amount сумма платежа должна точно соответствовать сумме товаров
 */
class PaymentRequest extends PayPartsRequest
{
    /** @var int кол-во частей платежа (2 - 25) */
    public $partsCount = self::PARTS_COUNT_MIN;

    /** @var string тип кредита */
    public $merchantType = self::MERCHANT_TYPE_PP;

    /**
     * @var ?int идентификатор схемы (optional)
     * Выделяется Банком. По умолчанию не передается. В расчете сигнатуры не используется.
     */
    public $scheme;

    /**
     * @var Product[] список товаров, каждый товар:
     * - string $name - наименование товара ([128]);
     * - float $price - цена за единицу (min=0.01);
     * - int $count - количество (min=1).
     */
    public $products;

    /**
     * @var ?string Идентификатор получателя (optional)
     * по умолчанию берется основной получатель. Установка основного получателя происходит в профиле магазина.
     */
    public $recipientId;

    /**
     * @var ?string адрес для получения ответа.
     * URL, на который Банк отправит результат сделки.
     */
    public $responseUrl;

    /**
     * @var ?string адрес для редиректа клиента
     * URL, на который Банк сделает редирект клиента
     */
    public $redirectUrl;

    /**
     * @var bool создание платежа без списания
     * Если true, то после резервирование денег с покупателя требуется подтверждение магазина
     * https://bw.gitbooks.io/api-oc/content/hold.html
     */
    public $hold = false;

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['partsCount', 'required'],
            ['partsCount', 'integer', 'min' => self::PARTS_COUNT_MIN, 'max' => self::PARTS_COUNT_MAX],
            ['partsCount', 'filter', 'filter' => 'intval'],

            ['merchantType', 'required'],
            ['merchantType', 'in', 'range' => array_keys(self::MERCHANT_TYPES)],

            ['scheme', 'default'],
            ['scheme', 'integer', 'min' => 0],
            ['scheme', 'filter', 'filter' => 'intval'],

            ['products', 'required'],
            ['products', function (string $attribute) {
                if (is_array($this->products)) {
                    foreach ($this->products as $prod) {
                        if (! $prod instanceof Product) {
                            $this->addError($attribute, 'Товар должен иметь тип Product');
                        } elseif (! $prod->validate()) {
                            $this->addError($attribute, (new ValidateException($prod))->getMessage());
                        }
                    }
                } else {
                    $this->addError($attribute, 'Товары должны быть массивом');
                }
            }],

            // проверяем сумму товаров после проверки самих товаров
            ['amount', 'number', 'min' => self::AMOUNT_MIN, 'max' => self::AMOUNT_MAX],

            ['recipientId', 'trim'],
            ['recipientId', 'default'],

            [['responseUrl', 'redirectUrl'], 'trim'],
            [['responseUrl', 'redirectUrl'], 'default'],
            [['responseUrl', 'redirectUrl'], 'url'],

            ['hold', 'default', 'value' => false],
            ['hold', 'boolean'],
            ['hold', 'filter', 'filter' => 'boolval']
        ]);
    }

    /** @var float */
    private $_amount;

    /**
     * Возвращает сумму товаров.
     *
     * @return float
     */
    public function getAmount(): float
    {
        if (empty($this->products)) {
            return 0;
        }

        if (! isset($this->_amount)) {
            $this->_amount = array_reduce($this->products, static function (float $amount, Product $prod) {
                return $amount + $prod->sum;
            }, 0);
        }

        return $this->_amount;
    }

    /**
     * @inheritDoc
     */
    protected function func(): string
    {
        return 'payment/' . ($this->hold ? 'hold' : 'create');
    }

    /**
     * @inheritDoc
     */
    protected function data(): array
    {
        return array_merge(parent::data(), [
            'amount' => sprintf('%.2f', $this->amount),
            'partsCount' => $this->partsCount,
            'merchantType' => $this->merchantType,
            'responseUrl' => $this->responseUrl,
            'redirectUrl' => $this->redirectUrl,
            'products' => array_map(static function (Product $prod) {
                return $prod->data;
            }, $this->products),
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
            str_replace('.', '', sprintf('%.2f', $this->amount)),
            $this->partsCount,
            $this->merchantType,
            $this->responseUrl,
            $this->redirectUrl,
            array_reduce($this->products, static function (string $s, Product $prod) {
                return $s . $prod->name . $prod->count .
                    str_replace('.', '', sprintf('%.2f', $prod->price));
            }, ''),
            $this->_module->password
        ]), true));
    }

    /**
     * @inheritDoc
     */
    public function send(): PayPartsResponse
    {
        $response = parent::send();

        // проверяем наличие токена
        if (empty($response->token)) {
            throw new Exception('Не получен токен: ' . Json::encode($response->attributes));
        }

        return $response;
    }
}
