<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 01:51:34
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\validate\ValidateException;
use yii\base\DynamicModel;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use function array_filter;
use function array_reduce;
use function base64_encode;
use function gettype;
use function implode;
use function is_array;
use function sha1;
use function sprintf;

/**
 * Запрос создания платежа.
 *
 * Если hold = true, то создается платеж с подтверждением после удержания денег с покупателя.
 * https://bw.gitbooks.io/api-oc/content/hold.html
 *
 * В ответ получаем token платежа, используя который, переадресовываем клиента
 * на страницу оплаты PayPartsModule::checkoutUrl
 *
 * @api https://api.privatbank.ua/#p24/partPaymentApi
 *
 * @property-read float $amount сумма платежа должна точно соответствовать сумме товаров
 */
class PaymentRequest extends AbstractRequest
{
    /** @var string [64] номер платежа (заказа) */
    public $orderId;

    /** @var int кол-во частей платежа (2 - 25) */
    public $partsCount;

    /** @var string тип кредита */
    public $merchantType = self::MERCHANT_TYPE_PP;

    /**
     * @var int|null идентификатор схемы (optional)
     * Выделяется Банком. По умолчанию не передается. В расчете сигнатуры не используется.
     */
    public $scheme;

    /**
     * @var array список товаров, каждый товар:
     * - string $name - наименование товара ([128]);
     * - float $price - цена за единицу (min=0.01);
     * - int $count - количество (min=1).
     */
    public $products;

    /**
     * @var string|null Идентификатор получателя (optional)
     * по умолчанию берется основной получатель. Установка основного получателя происходит в профиле магазина.
     */
    public $recipientId;

    /**
     * @var string|null адрес для получения ответа.
     * URL, на который Банк отправит результат сделки.
     */
    public $responseUrl;

    /**
     * @var string|null адрес для редиректа клиента
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
        return [
            ['orderId', 'trim'],
            ['orderId', 'required'],
            ['orderId', 'string', 'max' => 64],

            ['products', 'required'],
            ['products', 'validateProducts'],

            ['partsCount', 'required'],
            ['partsCount', 'integer', 'min' => self::PARTS_COUNT_MIN, 'max' => self::PARTS_COUNT_MAX],

            ['merchantType', 'required'],
            ['merchantType', 'in', 'range' => array_keys(self::MERCHANT_TYPES)],

            ['scheme', 'default'],
            ['scheme', 'integer'],

            [['recipientId', 'responseUrl', 'redirectUrl'], 'trim'],
            [['recipientId', 'responseUrl', 'redirectUrl'], 'default'],
        ];
    }

    /**
     * Проверка товаров.
     *
     * @param string $attribute
     */
    public function validateProducts(string $attribute)
    {
        if (empty($this->{$attribute})) {
            $this->addError($attribute, 'Требуется указать список товаров');
        } elseif (is_array($this->{$attribute})) {
            $prods = [];

            foreach ($this->{$attribute} as $prod) {
                try {
                    $prods[] = $this->validateProduct($prod);
                } catch (Exception $ex) {
                    $this->addError($attribute, $ex->getMessage());
                    break;
                }
            }

            $this->{$attribute} = $prods;

            // проверяем сумму товаров
            if ($this->amount < self::AMOUNT_MIN || $this->amount > self::AMOUNT_MAX) {
                $this->addError($attribute,
                    'Сумма товаров должна быть от ' . self::AMOUNT_MIN . ' до ' . self::AMOUNT_MAX
                );
            }
        } else {
            $this->addError($attribute, 'Некорректный тип товаров: ' . gettype($this->{$attribute}));
        }
    }

    /**
     * Проверка данных товара.
     *
     * @param mixed $prod
     * @return array данные товара после проверки
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function validateProduct($prod)
    {
        if (empty($prod)) {
            throw new Exception('Пустой товар');
        }

        if (! is_array($prod)) {
            throw new Exception('Некорректный тип товара: ' . gettype($prod));
        }

        $model = DynamicModel::validateData($prod, [
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 128],

            ['price', 'required'],
            ['price', 'number', 'min' => 0.01],
            ['price', 'filter', 'filter' => static function($price) {
                return (float)sprintf('%.2f', $price);
            }],

            ['count', 'required'],
            ['count', 'integer', 'min' => 1],
            ['count', 'filter', 'filter' => 'floatval']
        ]);

        if ($model->hasErrors()) {
            $errorAttr = array_keys($model->firstErrors)[0];

            throw new Exception($errorAttr . ': ' . $model->getFirstError($errorAttr));
        }

        return $model->attributes;
    }

    /** @var float */
    private $_amount;

    /**
     * Возвращает сумму товаров.
     *
     * @return float
     */
    public function getAmount()
    {
        if (empty($this->products)) {
            return 0;
        }

        if (! isset($this->_amount)) {
            $this->_amount = array_reduce($this->products, static function(float $amount, array $prod) {
                return $amount + $prod['price'] * $prod['count'];
            }, 0);
        }

        return $this->_amount;
    }

    /**
     * Возвращает сигнатуру данных
     *
     * @return string
     */
    protected function signature()
    {
        return base64_encode(sha1(implode('', [
            $this->module->password,
            $this->module->storeId,
            $this->orderId,
            (int)($this->amount * 100), // заменяем на копейки
            $this->partsCount,
            $this->merchantType,
            $this->responseUrl,
            $this->redirectUrl,
            array_reduce($this->products, static function(string $str, array $prod) {
                return $str . $prod['name'] . $prod['count'] . (int)($prod['price'] * 100.0);
            }, ''),
            $this->module->password
        ]), true));
    }

    /**
     * Возвращает данные для JSON.
     *
     * @return array
     * @throws ValidateException
     */
    protected function json()
    {
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        // для сигнатуры важен порядок данных
        return array_filter([
            'storeId' => $this->module->storeId,
            'orderId' => $this->orderId,
            'amount' => $this->amount,
            'partsCount' => $this->partsCount,
            'merchantType' => $this->merchantType,
            'responseUrl' => $this->responseUrl,
            'redirectUrl' => $this->redirectUrl,
            'products' => $this->products,
            'signature' => $this->signature()
        ], static function($val) {
            return $val !== null && $val !== '';
        });
    }

    /**
     * Отправляет запрос и возвращает токен.
     *
     * @return string токен платежа
     * @throws Exception
     */
    public function send()
    {
        $response = $this->sendData($this->hold ? 'hold' : 'create', $this->json());

        if (empty($response->token)) {
            throw new Exception('Не получен токен');
        }

        return $response->token;
    }
}
