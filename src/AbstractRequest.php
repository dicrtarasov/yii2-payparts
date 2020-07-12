<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 00:48:49
 */

declare(strict_types = 1);
namespace dicr\payparts;

use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * Базовый класс для запросов.
 *
 * @property-read PayPartsModule $module
 */
class AbstractRequest extends Model implements PayParts
{
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
        if (! $module instanceof PayPartsModule) {
            throw new InvalidArgumentException('module');
        }

        $this->_module = $module;

        parent::__construct($config);
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
     * Отправляет POST запрос.
     *
     * @param string $func
     * @param array $data
     * @return Response
     * @throws Exception
     */
    protected function sendData(string $func, array $data)
    {
        // HTTP POST
        $request = $this->module->httpClient->post($func, $data);
        $request->format = Client::FORMAT_JSON;

        // отправляем
        $response = $request->send();
        if (! $response->isOk) {
            throw new Exception('Ошибка запроса: ' . $response->statusCode);
        }

        // ответ
        $response->format = Client::FORMAT_JSON;
        $payPartsResponse = new Response();
        $payPartsResponse->load($response->data, '');

        if (! $payPartsResponse->validate()) {
            throw new Exception('Некорректный ответ: ' . $response->content);
        }

        // проверяем статус ответа
        if ($payPartsResponse->state !== self::STATE_SUCCESS) {
            throw new Exception('Ошибка: ' . $payPartsResponse->message);
        }

        return $payPartsResponse;
    }
}
