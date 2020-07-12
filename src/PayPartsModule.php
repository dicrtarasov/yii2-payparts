<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 01:14:07
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\helper\Url;
use dicr\http\CachingClient;
use Yii;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\httpclient\Client;
use yii\web\Application;
use yii\web\JsonParser;
use function array_merge;
use function http_build_query;
use function is_callable;
use function ob_end_clean;
use function ob_get_level;
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
class PayPartsModule extends Module implements PayParts
{
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

    /** @var callable|null function(\dicr\payparts\Response $response) обработчик успешных платежей */
    public $callbackHandler;

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

        if (! empty($this->callbackHandler) && ! is_callable($this->callbackHandler)) {
            throw new InvalidConfigException('callbackHandler');
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

    /**
     * Переадресует на страницу оплаты.
     *
     * @param string $token
     * @throws ExitException
     */
    public static function redirectCheckout(string $token)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        Yii::$app->end(0, Yii::$app->response->redirect(self::checkoutUrl($token)));
    }
}
