<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:07:52
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
    public function init() : void
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
        }
    }

    /** @var Client */
    private $_httpClient;

    /**
     * Возвращает HTTP-клиент.
     *
     * @return Client
     */
    public function getHttpClient() : Client
    {
        if ($this->_httpClient === null) {
            $this->_httpClient = new Client([
                'baseUrl' => $this->url
            ]);
        }

        return $this->_httpClient;
    }

    /**
     * Создает запрос на создание платежа.
     *
     * @param array $config
     * @return PaymentRequest
     */
    public function paymentRequest(array $config = []) : PaymentRequest
    {
        return new PaymentRequest($this, array_merge([
            'responseUrl' => Url::to('/' . $this->uniqueId . '/callback', true),
            'redirectUrl' => Url::to('/', true),
        ], $this->paymentRequestConfig, $config));
    }

    /**
     * Создает запрос на подтверждение платежа с удержанием.
     *
     * @param array $config
     * @return ConfirmRequest
     */
    public function confirmRequest(array $config = []) : ConfirmRequest
    {
        return new ConfirmRequest($this, $config);
    }

    /**
     * Создает запрос на отмену платежа с удержанием.
     *
     * @param array $config
     * @return CancelRequest
     */
    public function cancelRequest(array $config = []) : CancelRequest
    {
        return new CancelRequest($this, $config);
    }

    /**
     * Создает запрос на возврат выполненного платежа.
     *
     * @param array $config
     * @return DeclineRequest
     */
    public function declineRequest(array $config = []) : DeclineRequest
    {
        return new DeclineRequest($this, $config);
    }

    /**
     * Создает запрос на проверку статуса платежа.
     *
     * @param array $config
     * @return StateRequest
     */
    public function stateRequest(array $config = []) : StateRequest
    {
        return new StateRequest($this, $config);
    }

    /**
     * Создает запрос создания QrCode.
     *
     * @param array $config
     * @return QrRequest
     */
    public function qrRequest(array $config = []) : QrRequest
    {
        return new QrRequest($this, $config);
    }
}
