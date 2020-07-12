<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 12.07.20 23:00:01
 */

declare(strict_types = 1);
namespace dicr\payparts;

use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * Базовый класс для запросов.
 */
class AbstractRequest extends Model
{
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
        if (! $module instanceof PayPartsModule) {
            throw new InvalidArgumentException('module');
        }

        $this->module = $module;

        parent::__construct($config);
    }

    /**
     * Отправляет POST запрос.
     *
     * @param string $func
     * @param array $data
     * @return mixed
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
        $json = $response->data;
        if (empty($json)) {
            throw new Exception('Некорректный ответ: ' . $response->content);
        }

        // проверяем статус ответа
        if (($json['state'] ?? '') !== PayPartsModule::STATE_SUCCESS) {
            throw new Exception('Ошибка: ' . ($json['message'] ?? ''));
        }

        return $json;
    }
}
