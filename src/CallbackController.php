<?php
/*
 * @copyright 2019-2020 Dicr http://dicr.org
 * @author Igor A Tarasov <develop@dicr.org>
 * @license proprietary
 * @version 23.08.20 15:35:13
 */

declare(strict_types = 1);
namespace dicr\payparts;

use dicr\validate\ValidateException;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Controller;

use function call_user_func;

/**
 * Контроллер обработки ответов от ПриватБанк.
 *
 * @property-read PayPartsModule $module
 */
class CallbackController extends Controller implements PayParts
{
    /** @inheritDoc */
    public $enableCsrfValidation = false;

    /**
     * Обработка запросов от ПриватБанк с результатами платежей.
     *
     * @throws BadRequestHttpException
     */
    public function actionIndex()
    {
        Yii::debug('Оповещение PayParts: ' . Yii::$app->request->rawBody, __METHOD__);

        // получаем распарсенные данные JSON
        $response = new PayPartsResponse($this->module, Yii::$app->request->bodyParams);

        // проверяем наличие всех необходимых полей
        if (! $response->validate()) {
            throw new BadRequestHttpException('Некорректные данные запроса: ' . Yii::$app->request->rawBody,
                0, new ValidateException($response)
            );
        }

        if (! empty($this->module->callbackHandler)) {
            call_user_func($this->module->callbackHandler, $response);
        }
    }
}
