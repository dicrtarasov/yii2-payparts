<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 18:42:03
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\payparts\request\CancelRequest;
use dicr\payparts\request\ConfirmRequest;
use dicr\payparts\request\DeclineRequest;
use dicr\payparts\request\PaymentRequest;
use dicr\payparts\request\QrRequest;
use dicr\payparts\request\StateRequest;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\helpers\Url;
use yii\httpclient\Client;
use yii\web\Application;
use yii\web\JsonParser;

use function array_merge;
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
 * @api https://bw.gitbooks.io/api-oc/content/
 * @api https://api.privatbank.ua/#p24/partPaymentApi
 * @link https://payparts2.privatbank.ua личный кабинет
 * @link https://bw.gitbooks.io/api-oc/content/testdata.html тестовые данные
 */
class PayPartsModule extends Module implements PayParts
{
    /** @var string URL API */
    public $url = self::API_URL;

    /** @var string идентификатор магазина */
    public $storeId;

    /** @var string пароль */
    public $password;

    /** @var array */
    public $httpClientConfig = [];

    /** @var ?callable function(PayPartsResponse $response) обработчик callback-запросов от банка со статусами платежа */
    public $callbackHandler;

    /** @var array конфиг по-умолчанию для запросов создания платежа */
    public $paymentRequestConfig = [];

    /** @inheritDoc */
    public $controllerNamespace = __NAMESPACE__;

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (empty($this->url)) {
            throw new InvalidConfigException('url');
        }

        $this->storeId = trim((string)$this->storeId);
        if (empty($this->storeId) || strlen($this->storeId) > 20) {
            throw new InvalidConfigException('storeId');
        }

        $this->password = trim((string)$this->password);
        if (empty($this->password)) {
            throw new InvalidConfigException('password');
        }

        if (! empty($this->callbackHandler) && ! is_callable($this->callbackHandler)) {
            throw new InvalidConfigException('callbackHandler');
        }

        // для разбора raw http json запросов от ПриватБанк
        if (Yii::$app instanceof Application) {
            Yii::$app->request->parsers['application/json'] = JsonParser::class;

            $this->paymentRequestConfig = array_merge([
                'responseUrl' => Url::to(['/' . $this->uniqueId . '/callback'], true),
                'redirectUrl' => Url::to(Yii::$app->homeUrl, true),
            ], $this->paymentRequestConfig ?: []);
        }

        // адреса по-умолчанию
        $this->paymentRequestConfig = array_merge([
            'responseUrl' => Url::to('/' . $this->uniqueId . '/callback', true),
            'redirectUrl' => Url::to('/', true),
        ], $this->paymentRequestConfig ?: []);
    }

    /** @var Client */
    private $_httpClient;

    /**
     * Возвращает HTTP-клиент.
     *
     * @return Client
     * @throws InvalidConfigException
     */
    public function getHttpClient()
    {
        if (! isset($this->_httpClient)) {
            $this->_httpClient = Yii::createObject(array_merge([
                'class' => Client::class,
                'baseUrl' => $this->url,
            ], $this->httpClientConfig ?: []));
        }

        return $this->_httpClient;
    }

    /**
     * Создает запрос.
     *
     * @param array $config
     * @return PayPartsRequest
     * @throws InvalidConfigException
     */
    public function createRequest(array $config)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Yii::createObject($config, [$this]);
    }

    /**
     * Создает запрос на создание платежа.
     *
     * @param array $config
     * @return PaymentRequest
     * @throws InvalidConfigException
     */
    public function createPaymentRequest(array $config = []): PaymentRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createRequest(array_merge([
            'class' => PaymentRequest::class
        ], $this->paymentRequestConfig ?: [], $config));
    }

    /**
     * Создает запрос на подтверждение платежа с удержанием.
     *
     * @param array $config
     * @return ConfirmRequest
     * @throws InvalidConfigException
     */
    public function createConfirmRequest(array $config = []): ConfirmRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createRequest(array_merge([
            'class' => ConfirmRequest::class
        ], $config));
    }

    /**
     * Создает запрос на отмену платежа с удержанием.
     *
     * @param array $config
     * @return CancelRequest
     * @throws InvalidConfigException
     */
    public function createCancelRequest(array $config = []): CancelRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createRequest(array_merge([
            'class' => CancelRequest::class
        ], $config));
    }

    /**
     * Создает запрос на возврат выполненного платежа.
     *
     * @param array $config
     * @return DeclineRequest
     * @throws InvalidConfigException
     */
    public function createDeclineRequest(array $config = []): DeclineRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createRequest(array_merge([
            'class' => DeclineRequest::class
        ], $config));
    }

    /**
     * Создает запрос на проверку статуса платежа.
     *
     * @param array $config
     * @return StateRequest
     * @throws InvalidConfigException
     */
    public function createStateRequest(array $config = []): StateRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createRequest(array_merge([
            'class' => StateRequest::class
        ], $config));
    }

    /**
     * Создает запрос создания QrCode.
     *
     * @param array $config
     * @return QrRequest
     * @throws InvalidConfigException
     */
    public function createQrRequest(array $config = []): QrRequest
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->createRequest(array_merge([
            'class' => QrRequest::class
        ], $config));
    }
}
