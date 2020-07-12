<?php
/**
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 12.07.20 21:14:32
 */

declare(strict_types = 1);
namespace dicr\payparts;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use function array_key_exists;
use function base64_encode;
use function call_user_func;
use function sha1;

/**
 * Контроллер обработки ответов от ПриватБанк.
 *
 * @property-read PayPartsModule $module
 */
class CallbackController extends Controller
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
        $json = Yii::$app->request->rawBody;

        // проверяем наличие всех необходимых полей
        if (! isset($json['storeId'], $json['orderId'], $json['paymentState'], $json['message'], $json['signature'])) {
            throw new BadRequestHttpException('Некорректные данные json');
        }

        // проверяем правильность магазина
        if ($json['storeId'] !== $this->module->storeId) {
            throw new BadRequestHttpException('Неверный storeId');
        }

        // рассчитываем сигнатуру расчет
        $signature = base64_encode(sha1(
            $this->module->password . $json['storeId'] . $json['orderId'] . $json['paymentState'] .
            $json['message'] . $this->module->password, true
        ));

        // проверяем сигнатуру
        if ($json['signature'] !== $signature) {
            throw new BadRequestHttpException('Некорректная сигнатура');
        }

        // состояние платежа
        if (! array_key_exists($json['paymentState'], PayPartsModule::STATES)) {
            Yii::error('Неизвестный статус платежа: ' . $json['paymentState'], __METHOD__);
            throw new BadRequestHttpException('Неизвестный статус платежа');
        }

        Yii::info(
            'Статус платежа заказ №' . $json['orderId'] . ': ' . PayPartsModule::STATES[$json['paymentState']],
            __METHOD__
        );

        if ($json['paymentState'] === PayPartsModule::STATE_SUCCESS) {
            if (! empty($this->module->successHandler)) {
                call_user_func($this->module->successHandler, $json['orderId']);
            }
        } elseif ($json['paymentState'] === PayPartsModule::STATE_FAIL) {
            if (! empty($this->module->failureHandler)) {
                call_user_func($this->module->failureHandler, $json['orderId'], $json['message']);
            }
        }
    }
}
