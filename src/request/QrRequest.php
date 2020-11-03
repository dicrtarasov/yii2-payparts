<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 03.11.20 23:13:33
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\json\JsonEntity;
use dicr\payparts\PayParts;
use dicr\payparts\PayPartsModule;
use dicr\validate\ValidateException;
use Yii;
use yii\base\Exception;
use yii\httpclient\Client;

/**
 * Генерация QR-кода для подтверждения сделки.
 *
 * @link https://bw.gitbooks.io/api-oc/content/qr_code.html
 */
class QrRequest extends JsonEntity implements PayParts
{
    /** @var string токен, полученный при создании платежа */
    public $token;

    /** @var ?mixed размер картинки */
    public $size;

    /** @var float окончательная сумма покупки */
    public $amount;

    /**
     * @var ?string тип рассрочки
     * @see PayParts::MERCHANT_TYPES
     */
    public $type;

    /** @var PayPartsModule */
    private $module;

    /**
     * QrRequest constructor.
     *
     * @param PayPartsModule $module
     * @param array $config
     */
    public function __construct(PayPartsModule $module, $config = [])
    {
        $this->module = $module;

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function rules() : array
    {
        // не учитываем parent::orderId
        return [
            ['token', 'trim'],
            ['token', 'required'],

            ['size', 'default'],

            ['amount', 'required'],
            ['amount', 'number', 'min' => 0.01],
            ['amount', 'filter', 'filter' => static function ($val) : float {
                return round((float)$val, 2);
            }],

            ['type', 'default'],
            ['type', 'in', 'range' => self::MERCHANT_TYPES]
        ];
    }

    /**
     * Отправляет запрос.
     *
     * @return string QR-код
     * @throws Exception
     */
    public function send() : string
    {
        // валидация полей
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        // фильтруем данные
        $data = array_filter($this->getJson(), static function ($val) : bool {
            return $val !== null && $val !== '' && $val !== [];
        });

        // запрос
        $request = $this->module->httpClient->get(self::QR_URL, $data, [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'UTF-8',
        ]);

        // получаем ответ
        Yii::debug('Запрос: ' . $request->toString(), __METHOD__);
        $response = $request->send();
        Yii::debug('Ответ: ' . $response->toString(), __METHOD__);

        if (! $response->isOk) {
            throw new Exception('HTTP error: ' . $response->statusCode);
        }

        $response->format = Client::FORMAT_JSON;

        // проверяем состояние
        if (($response->data['state'] ?? '') !== self::STATE_SUCCESS) {
            throw new Exception('Ошибка запроса: ' . ($response->data['message'] ?? $response->content));
        }

        // проверяем наличие результата
        $qr = (string)($response->data['qr'] ?? '');
        if ($qr === '') {
            throw new Exception('Не получен QR-код: ' . $response->content);
        }

        // возвращаем QR-код
        return $qr;
    }
}
