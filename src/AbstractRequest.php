<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 19.07.20 05:05:00
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
 * @property-read PaypartsModule $module
 */
class AbstractRequest extends Model implements Payparts
{
    /** @var PaypartsModule */
    protected $_module;

    /**
     * AbstractRequest constructor.
     *
     * @param PaypartsModule $module
     * @param array $config
     */
    public function __construct(PaypartsModule $module, array $config = [])
    {
        if (! $module instanceof PaypartsModule) {
            throw new InvalidArgumentException('module');
        }

        $this->_module = $module;

        parent::__construct($config);
    }

    /**
     * Возвращает модуль.
     *
     * @return PaypartsModule
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
     * @return PaymentResponse
     * @throws Exception
     */
    protected function sendData(string $func, array $data)
    {
        // HTTP POST
        $request = $this->module->httpClient->post($func, $data);
        $request->format = Client::FORMAT_JSON;
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept-Encoding', 'UTF-8');

        // отправляем
        $response = $request->send();
        $response->format = Client::FORMAT_JSON;
        if (! $response->isOk) {
            throw new Exception('Ошибка запроса: ' . $response->statusCode);
        }

        // ответ
        $payPartsResponse = new PaymentResponse();
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
