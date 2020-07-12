<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 13.07.20 00:47:25
 */

declare(strict_types = 1);
namespace dicr\payparts;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use function base64_encode;
use function call_user_func;
use function sha1;

/**
 * Контроллер обработки ответов от ПриватБанк.
 *
 * @property-read PayPartsModule $module
 */
class CallbackController extends Controller implements PayParts
{
    /**
     * @inheritDoc
     *
     * Отключаем проверку CSRF для запросов ПриватБанк.
     */
    public $enableCsrfValidation = false;

    /**
     * Обработка запросов от ПриватБанк с результатами платежей.
     *
     * @throws BadRequestHttpException
     */
    public function actionIndex()
    {
        // получаем распарсенные данные JSON
        $response = new Response();
        $response->load(Yii::$app->request->rawBody, '');

        // проверяем наличие всех необходимых полей
        if (! $response->validate()) {
            throw new BadRequestHttpException('Некорректные данные json');
        }

        // проверяем правильность магазина
        if ($response->storeId !== $this->module->storeId) {
            throw new BadRequestHttpException('Неверный storeId');
        }

        // рассчитываем сигнатуру расчет
        $signature = base64_encode(sha1(
            $this->module->password . $response->storeId . $response->orderId . $response->paymentState .
            $response->message . $this->module->password, true
        ));

        // проверяем сигнатуру
        if ($response->signature !== $signature) {
            throw new BadRequestHttpException('Некорректная сигнатура');
        }

        Yii::info(
            'Статус платежа заказ №' . $response->orderId . ': ' . self::STATES[$response->paymentState],
            __METHOD__
        );

        if (! empty($this->module->callbackHandler)) {
            call_user_func($this->module->callbackHandler, $response);
        }
    }
}
