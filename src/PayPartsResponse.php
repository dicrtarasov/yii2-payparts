<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:21:21
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\json\JsonEntity;
use RuntimeException;
use Throwable;
use Yii;
use yii\base\Exception;

use function base64_encode;
use function http_build_query;
use function ob_end_clean;
use function ob_get_level;
use function rtrim;
use function sha1;

/**
 * Ответ на запрос.
 *
 * @property-read PayPartsModule $module
 * @property-read ?string $paymentUrl URL для переадресации на платеж (в случае наличия token)
 */
class PayPartsResponse extends JsonEntity implements PayParts
{
    /**
     * @var ?string результат отработки запроса.
     * Возвращается в ответы на запросы.
     */
    public $state;

    /**
     * @var ?string статус заявки на кредит.
     * Возвращается в запросе статуса заявки, а также в callback-запросах банка.
     */
    public $paymentState;

    /**
     * @var ?string идентификатор магазина.
     */
    public $storeId;

    /**
     * @var ?string уникальный номер платежа (заказа магазина).
     */
    public $orderId;

    /**
     * @var ?string токен платежа.
     * Возвращается на запрос создания платежа.
     */
    public $token;

    /**
     * @var ?string текст сообщения.
     * Возвращается в некоторых успешных запросах, а также в случае ошибки.
     */
    public $message;

    /** @var string сигнатура данных */
    public $signature;

    /**
     * @var string сообщение об ошибке.
     * Недокументированное поле.
     */
    public $errorMessage;

    /**
     * @var string локаль
     * Недокументированное поле в ответе с ошибкой.
     */
    public $locale;

    /** @var PayPartsModule */
    private $_module;

    /**
     * PaymentResponse constructor.
     *
     * @param PayPartsModule $module
     * @param array $config
     */
    public function __construct(PayPartsModule $module, $config = [])
    {
        $this->_module = $module;

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function rules() : array
    {
        return [
            // state возвращается в ответе на запрос, а paymentState в callback-запросе
            ['state', 'trim'],
            ['state', 'default'],
            ['state', 'required', 'when' => function () : bool {
                return empty($this->paymentState);
            }],

            ['paymentState', 'trim'],
            ['paymentState', 'default'],
            ['paymentState', 'required', 'when' => function () : bool {
                return empty($this->state);
            }],

            ['storeId', 'trim'],
            ['storeId', 'default'],
            ['storeId', function (string $attribute) {
                if (! empty($this->storeId) && $this->storeId !== $this->_module->storeId) {
                    $this->addError($attribute, 'Некорректный storeId в ответе: ' . $this->storeId);
                }
            }],

            ['orderId', 'trim'],
            ['orderId', 'default'],

            ['token', 'trim'],
            ['token', 'default'],

            [['message', 'errorMessage', 'locale'], 'trim'],
            [['message', 'errorMessage', 'locale'], 'default'],

            ['signature', 'trim'],
            ['signature', function (string $attribute) {
                if ($this->signature !== $this->signature()) {
                    $this->addError($attribute, 'Некорректная сигнатура');
                }
            }]
        ];
    }

    /**
     * Модуль PayParts
     *
     * @return PayPartsModule
     */
    public function getModule() : PayPartsModule
    {
        return $this->_module;
    }

    /**
     * Рассчитывает сигнатуру данных.
     *
     * @return string
     */
    private function signature() : string
    {
        // рассчитываем сигнатуру расчет
        return base64_encode(sha1(
            $this->_module->password . $this->state . $this->storeId . $this->orderId . $this->token .
            $this->paymentState . $this->message . $this->_module->password, true
        ));
    }

    /**
     * Адрес страницы переадресации покупателя для оплаты.
     *
     * @return ?string
     * @link https://bw.gitbooks.io/api-oc/content/redirect.html
     */
    public function getPaymentUrl() : ?string
    {
        return empty($this->token) ? null : rtrim($this->_module->url, '/') . '/payment?' .
            http_build_query(['token' => $this->token]);
    }

    /**
     * Переадресация на страницу оплаты.
     *
     * @throws Exception
     */
    public function redirectCheckout() : void
    {
        $url = $this->paymentUrl;
        if (empty($url)) {
            throw new Exception('Не задан token для редиректа');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        try {
            Yii::$app->end(0, Yii::$app->response->redirect($url));
        } catch (Throwable $ex) {
            throw new RuntimeException('Неизвестная ошибка');
        }
    }
}
