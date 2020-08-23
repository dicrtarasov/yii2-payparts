<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 24.08.20 01:40:45
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\validate\ValidateException;
use yii\base\Exception;
use yii\base\Model;
use yii\httpclient\Client;

use function array_filter;
use function base64_encode;
use function implode;
use function sha1;

/**
 * Базовый класс для запросов.
 *
 * @property-read PayPartsModule $module
 */
abstract class PayPartsRequest extends Model implements PayParts
{
    /** @var string */
    public $orderId;

    /** @var PayPartsModule */
    protected $_module;

    /**
     * AbstractRequest constructor.
     *
     * @param PayPartsModule $module
     * @param array $config
     */
    public function __construct(PayPartsModule $module, array $config = [])
    {
        $this->_module = $module;

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            ['orderId', 'trim'],
            ['orderId', 'required'],
            ['orderId', 'string', 'max' => 64],
        ];
    }

    /**
     * Возвращает модуль.
     *
     * @return PayPartsModule
     */
    public function getModule()
    {
        return $this->_module;
    }

    /**
     * URL
     *
     * @return string
     */
    abstract protected function url(): string;

    /**
     * Метод HTTP-запроса.
     *
     * @return string
     */
    protected function method(): string
    {
        return 'post';
    }

    /**
     * Данные для JSON.
     *
     * @return array
     */
    protected function data(): array
    {
        return [
            'orderId' => $this->orderId
        ];
    }

    /**
     * Сигнатура данных.
     *
     * @return string
     */
    protected function signature(): string
    {
        return base64_encode(sha1(implode('', [
            $this->_module->password,
            $this->_module->storeId,
            $this->orderId,
            $this->_module->password
        ]), true));
    }

    /**
     * Отправка запроса.
     *
     * @return PayPartsResponse
     * @throws Exception
     */
    public function send(): PayPartsResponse
    {
        // проверяем поля
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        // получаем и фильтруем данные JSON
        $data = array_filter($this->data(), static function ($val) {
            return $val !== null && $val !== '' && $val !== [];
        });

        // добавляем общие поля
        $data['storeId'] = $this->_module->storeId;
        $data['signature'] = $this->signature();

        // HTTP POST
        $request = $this->_module->httpClient->createRequest()
            ->setUrl($this->url())
            ->setMethod($this->method())
            ->setHeaders([
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept' => 'application/json',
                'Accept-Encoding' => 'UTF-8'
            ])
            ->setData($data)
            ->setFormat(Client::FORMAT_JSON);

        // отправляем
        $response = $request->send();
        $response->format = Client::FORMAT_JSON;
        if (! $response->isOk) {
            throw new Exception('Ошибка запроса: ' . $response->statusCode);
        }

        // ответ
        $payPartsResponse = new PayPartsResponse($this->_module, $response->data);

        // проверяем ответ
        if (! $payPartsResponse->validate()) {
            throw new Exception('Некорректный ответ: ' . $response->content . ': ' .
                (new ValidateException($payPartsResponse))->getMessage()
            );
        }

        // проверяем номер заказа в ответе
        if (! empty($payPartsResponse->orderId) && $payPartsResponse->orderId !== $this->orderId) {
            throw new Exception('Некорректный orderId в ответе: ' . $payPartsResponse->orderId);
        }

        // проверяем статус запроса
        if ($payPartsResponse->state !== self::STATE_SUCCESS) {
            throw new Exception('Ошибка: ' . ($payPartsResponse->message ?: $payPartsResponse->errorMessage));
        }

        // возвращаем ответ
        return $payPartsResponse;
    }
}
