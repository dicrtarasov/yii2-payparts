<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 12.07.20 23:16:29
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\helper\Url;
use dicr\http\CachingClient;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\httpclient\Client;
use yii\web\Application;
use yii\web\JsonParser;
use function array_merge;
use function http_build_query;
use function is_callable;
use function strlen;

/**
 * Модуль оплаты частями от ПриватБанк.
 *
 * Тестовые карты:
 * - 0000111122223333 10/20 123 (успешный платеж)
 * - 0000111122224444 10/20 123 (отказ от платежа)
 *
 * Данные для тестовой авторизации в личном кабинете:
 * телефон: 0988748970
 * пароль: password8970
 *
 * Sandbox (для теста запросов): https://payparts2.privatbank.ua/ipp/sandbox#!create
 *
 * @property-read Client $httpClient
 *
 * @api https://api.privatbank.ua/#p24/partPaymentApi
 * @api https://bw.gitbooks.io/api-oc/content/
 * @link https://payparts2.privatbank.ua личный кабинет
 * @link https://bw.gitbooks.io/api-oc/content/testdata.html тестовые данные
 */
class PayPartsModule extends Module
{
    /** @var string адрес API */
    public const API_URL = 'https://payparts2.privatbank.ua/ipp/v2/payment';

    /** @var string Мгновенная рассрочка */
    public const MERCHANT_TYPE_II = 'II';

    /** @var string Мгновенная рассрочка. Акционная. */
    public const MERCHANT_TYPE_IA = 'IA';

    /** @var string Оплата частями */
    public const MERCHANT_TYPE_PP = 'PP';

    /** @var string Деньги в периоде */
    public const MERCHANT_TYPE_PB = 'PB';

    /** @var string типы рассрочек */
    public const MERCHANT_TYPES = [
        self::MERCHANT_TYPE_II => 'Мгновенная рассрочка',
        self::MERCHANT_TYPE_IA => 'Мгновенная рассрочка (акционная)',
        self::MERCHANT_TYPE_PP => 'Оплата частями',
        self::MERCHANT_TYPE_PB => 'Оплата частями (деньги в периоде)'
    ];

    /** @var int минимальная сумма */
    public const AMOUNT_MIN = 300;

    /** @var int максимальная сумма */
    public const AMOUNT_MAX = 50000;

    /** @var int минимальное кол-во частей */
    public const PARTS_COUNT_MIN = 2;

    /** @var int максимальное кол-во частей */
    public const PARTS_COUNT_MAX = 25;

    /** @var string платеж создан */
    public const STATE_CREATED = 'CREATED';

    /** @var string отменен клиентов */
    public const STATE_CANCELED = 'CANCELED';

    /** @var string платеж прошел */
    public const STATE_SUCCESS = 'SUCCESS';

    /** @var string ошибка при создании платежа */
    public const STATE_FAIL = 'FAIL';

    /** @var string ожидание оплаты клиентом */
    public const STATE_CLIENT_WAIT = 'CLIENT_WAIT';

    /** @var string подтверждение клиентом пароля */
    public const STATE_OTP_WAITING = 'OTP_WAITING';

    /** @var string создание контракта для платежа */
    public const STATE_PP_CREATION = 'PP_CREATION';

    /** @var string деньги зарезервированы */
    public const STATE_LOCKED = 'LOCKED';

    /** @var string[] статусы платежей */
    public const STATES = [
        self::STATE_CREATED => 'платеж создан',
        self::STATE_CANCELED => 'платеж отменен клиентом',
        self::STATE_SUCCESS => 'платеж совершен',
        self::STATE_FAIL => 'ошибка создания платежа',
        self::STATE_CLIENT_WAIT => 'ожидание оплаты клиентом',
        self::STATE_OTP_WAITING => 'подтверждение клиентом ОТП-пароля',
        self::STATE_PP_CREATION => 'создание контракта платежа',
        self::STATE_LOCKED => 'ожидает подтверждение магазином'
    ];

    /** @var string тестовый storeId */
    public const TEST_STORE_ID = '4AAD1369CF734B64B70F';

    /** @var string тестовый пароль */
    public const TEST_PASSWORD = '75bef16bfdce4d0e9c0ad5a19b9940df';

    /** @var string идентификатор магазина */
    public $storeId;

    /** @var string пароль */
    public $password;

    /** @var array конфиг по-умолчанию для запросов создания платежа */
    public $paymentRequestConfig = [];

    /** @var array */
    public $httpClientConfig = [
        'baseUrl' => self::API_URL,
        'class' => CachingClient::class,
        'requestConfig' => [
            'format' => Client::FORMAT_JSON,
            'headers' => [
                // требуется наличие в запросе по документации
                'Accept' => 'application/json',
                'Accept-Encoding' => 'UTF-8',
            ]
        ]
    ];

    /** @var callable|null function(string $orderId) обработчик успешных платежей */
    public $successHandler;

    /** @var callable|null function(string $orderId, string $errorMessage) обработчик неуспешных платежей */
    public $failureHandler;

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        $this->controllerNamespace = 'dicr\\payparts';

        // для разбора raw http json запросов от ПриватБанк
        if (Yii::$app instanceof Application) {
            Yii::$app->request->parsers['application/json'] = JsonParser::class;
        }

        parent::init();

        $this->storeId = trim((string)$this->storeId);
        if (empty($this->storeId) || strlen($this->storeId) > 20) {
            throw new InvalidConfigException('storeId');
        }

        $this->password = trim((string)$this->password);
        if (empty($this->password)) {
            throw new InvalidConfigException('password');
        }

        if (Yii::$app instanceof Application) {
            $this->paymentRequestConfig = array_merge([
                'responseUrl' => Url::to(['/' . $this->uniqueId . '/callback'], true),
                'redirectUrl' => Url::to(Yii::$app->homeUrl, true),
            ], $this->paymentRequestConfig ?: []);
        }

        if (! empty($this->successHandler) && ! is_callable($this->successHandler)) {
            throw new InvalidConfigException('successHandler');
        }

        if (! empty($this->failureHandler) && ! is_callable($this->failureHandler)) {
            throw new InvalidConfigException('failureHandler');
        }
    }

    /**
     * Возвращает HTTP-клиент.
     *
     * @return Client
     * @throws InvalidConfigException
     */
    public function getHttpClient()
    {
        /** @var Client $client */
        static $client;

        if (! isset($client)) {
            $client = Yii::createObject($this->httpClientConfig);
        }

        return $client;
    }

    /**
     * Создает запрос на создание платежа.
     *
     * @param array $config
     * @return PaymentRequest
     */
    public function createPaymentRequest(array $config = [])
    {
        return new PaymentRequest($this, array_merge($this->paymentRequestConfig, $config));
    }

    /**
     * Создает запрос на подтверждение платежа с удержанием.
     *
     * @param array $config
     * @return ConfirmRequest
     */
    public function createConfirmRequest(array $config = [])
    {
        return new ConfirmRequest($this, $config);
    }

    /**
     * Создает запрос на отмену платежа с удержанием.
     *
     * @param array $config
     * @return CancelRequest
     */
    public function createCancelRequest(array $config = [])
    {
        return new CancelRequest($this, $config);
    }

    /**
     * Создает запрос на возврат выполненного платежа.
     *
     * @param array $config
     * @return ReturnRequest
     */
    public function createReturnRequest(array $config = [])
    {
        return new ReturnRequest($this, $config);
    }

    /**
     * Адрес страницы переадресации покупателя для оплаты.
     *
     * @param string $token токен платежа, полученный в запросе создания платежа.
     * @return string
     */
    public static function checkoutUrl(string $token)
    {
        return self::API_URL . '/payment?' . http_build_query(['token' => $token]);
    }
}
