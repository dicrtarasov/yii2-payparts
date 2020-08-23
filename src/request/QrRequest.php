<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 24.08.20 01:43:45
 */

declare(strict_types = 1);
namespace dicr\payparts\request;

use dicr\payparts\PayParts;
use dicr\payparts\PayPartsModule;
use dicr\validate\ValidateException;
use Yii;
use yii\base\Exception;
use yii\base\Model;
use yii\httpclient\Client;

/**
 * Генерация QR-кода для подтверждения сделки.
 *
 * @link https://bw.gitbooks.io/api-oc/content/qr_code.html
 */
class QrRequest extends Model implements PayParts
{
    /** @var string токен, полученный при создании платежа */
    public $token;

    /** @var ?mixed размер картинки */
    public $size;

    /** @var float окончательная сумма покупки */
    public $amount;

    /**
     * @var string тип рассрочки
     * @see PayParts::MERCHANT_TYPES
     */
    public $type;

    /** @var PayPartsModule */
    private $_module;

    /**
     * QrRequest constructor.
     *
     * @param PayPartsModule $module
     * @param array $config
     */
    public function __construct(PayPartsModule $module, $config = [])
    {
        $this->_module = $module;

        parent::__construct($config);
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        // не учитываем parent::orderId
        return [
            ['token', 'trim'],
            ['token', 'required'],

            ['size', 'default'],

            ['amount', 'required'],
            ['amount', 'number', 'min' => 0.01],
            ['amount', 'filter', 'filter' => 'floatval'],

            ['type', 'default'],
            ['type', 'in', 'range' => self::MERCHANT_TYPES]
        ];
    }

    /**
     * Данные для запроса.
     *
     * @return array
     */
    protected function data(): array
    {
        return [
            'token' => $this->token,
            'size' => $this->size,
            'amount' => $this->amount,
            'type' => $this->type
        ];
    }

    /**
     * Отправляет запрос.
     *
     * @return string QR-код
     * @throws Exception
     * @throws ValidateException
     * @throws \yii\httpclient\Exception
     */
    public function send(): string
    {
        // валидация полей
        if (! $this->validate()) {
            throw new ValidateException($this);
        }

        // фильтруем данные
        $data = array_filter($this->data(), static function ($val) {
            return $val !== null && $val !== '' && $val !== [];
        });

        // запрос
        $request = $this->_module->httpClient->get(self::QR_URL, $data, [
            'Accept' => 'application/json',
            'Accept-Encoding' => 'UTF-8',
        ]);

        Yii::debug('Запрос на генерацию QR: ' . $request->toString());

        // получаем ответ
        $response = $request->send();
        $response->format = Client::FORMAT_JSON;
        if (! $response->isOk) {
            throw new Exception('Ошибка запроса: ' . $response->statusCode);
        }

        // проверяем состояние
        $state = $response->data['state'] ?? '';
        if ($state !== self::STATE_SUCCESS) {
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
