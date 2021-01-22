<?php
/*
 * @copyright 2019-2021 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.01.21 02:45:12
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\json\JsonEntity;
use dicr\validate\ValidateException;
use Yii;
use yii\base\Exception;
use yii\httpclient\Client;

use function array_filter;
use function base64_encode;
use function implode;
use function sha1;

/**
 * Базовый класс для запросов.
 */
abstract class PayPartsRequest extends JsonEntity implements PayParts
{
    /** @var string */
    public $orderId;

    /** @var PayPartsModule */
    protected $module;

    /**
     * AbstractRequest constructor.
     *
     * @param PayPartsModule $module
     * @param array $config
     */
    public function __construct(PayPartsModule $module, array $config = [])
    {
        $this->module = $module;

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function attributeFields() : array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function rules() : array
    {
        return [
            ['orderId', 'trim'],
            ['orderId', 'required'],
            ['orderId', 'string', 'max' => 64],
        ];
    }

    /**
     * URL
     *
     * @return string
     */
    abstract protected function url() : string;

    /**
     * Метод HTTP-запроса.
     *
     * @return string
     */
    protected function method() : string
    {
        return 'post';
    }

    /**
     * Сигнатура данных.
     *
     * @return string
     */
    protected function signature() : string
    {
        return base64_encode(sha1(implode('', [
            $this->module->password,
            $this->module->storeId,
            $this->orderId,
            $this->module->password
        ]), true));
    }

    /**
     * Отправка запроса.
     *
     * @return PayPartsResponse
     * @throws Exception
     */
    public function send() : PayPartsResponse
    {
        // проверяем поля
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        // получаем и фильтруем данные JSON
        $data = array_filter(array_merge($this->getJson(), [
            'storeId' => $this->module->storeId,
            'signature' => $this->signature()
        ]), static fn($val): bool => $val !== null && $val !== '' && $val !== []);

        // HTTP POST
        $req = $this->module->httpClient->createRequest()
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
        Yii::debug('Запрос: ' . $req->toString(), __METHOD__);
        $res = $req->send();
        Yii::debug('Ответ: ' . $res->toString(), __METHOD__);

        if (! $res->isOk) {
            throw new Exception('HTTP error: ' . $res->statusCode);
        }

        // ответ
        $res->format = Client::FORMAT_JSON;
        $payPartsResponse = new PayPartsResponse($this->module, [
            'json' => $res->data
        ]);

        // проверяем ответ
        if (! $payPartsResponse->validate()) {
            throw new Exception('Некорректный ответ: ' . $res->content . ': ' .
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
