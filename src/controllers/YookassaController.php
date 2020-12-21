<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\yookassa\controllers;

use skeeks\cms\shop\models\ShopBill;
use skeeks\cms\shop\models\ShopPayment;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use YooKassa\Client;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class YookassaController extends Controller
{

    /**
     * @var bool
     */
    public $enableCsrfValidation = false;


    /**
     *
     * Адрес для http уведомлений
     *
     * @return string|\yii\web\Response
     * @throws \YooKassa\Common\Exceptions\ApiException
     * @throws \YooKassa\Common\Exceptions\BadApiRequestException
     * @throws \YooKassa\Common\Exceptions\ExtensionNotFoundException
     * @throws \YooKassa\Common\Exceptions\ForbiddenException
     * @throws \YooKassa\Common\Exceptions\InternalServerError
     * @throws \YooKassa\Common\Exceptions\NotFoundException
     * @throws \YooKassa\Common\Exceptions\ResponseProcessingException
     * @throws \YooKassa\Common\Exceptions\TooManyRequestsException
     * @throws \YooKassa\Common\Exceptions\UnauthorizedException
     */
    public function actionPaymentListener()
    {
        \Yii::info(__METHOD__, self::class);
        $data = json_decode(file_get_contents('php://input'), true);
        \Yii::info(print_r($data, true), self::class);

        $paymentId = ArrayHelper::getValue($data, "object.id");
        $status = ArrayHelper::getValue($data, "object.status");

        /**
         * todo:учесть мультисайтовость
         * @var ShopBill $shopBill
         */
        $shopBill = ShopBill::find()->andWhere(['external_id' => $paymentId])->one();
        \Yii::info("Оплата: ".print_r($shopBill->id, true), self::class);
        
        if (!$shopBill) {
            throw new Exception("Не найден платеж на сайте");
        }

        /*
         * @var $yooKassa \skeeks\cms\shop\paySystems\YandexKassaPaySystem
         */
        $yooKassa = $shopBill->shopPaySystem->handler;

        if ($shopBill->paid_at) {
            \Yii::info("Платеж: ".$shopBill->id." уже оплаечен", self::class);
            return "Ok";
        }

        \Yii::info("Запрос информации о платеже: ".print_r([
            'shop_id'    => $yooKassa->shop_id,
            'secret_key' => $yooKassa->secret_key,
        ], true), self::class);


        $client = new Client();
        $client->setAuth($yooKassa->shop_id, $yooKassa->secret_key);
        $payment = $client->getPaymentInfo($paymentId);

        \Yii::info("Информация о платеже в yandex kassa: " . print_r($payment, true), self::class);

        if ($payment->status == "waiting_for_capture") {

            $money = $shopBill->money->convertToCurrency("RUB");

            $idempotenceKey = uniqid('', true);
            $response = $client->capturePayment(
                [
                    'amount' => [
                        'value'    => $money->amount,
                        'currency' => 'RUB',
                    ],
                ],
                $paymentId,
                $idempotenceKey
            );

            \Yii::info("Подтверждение оплаты: " . print_r($response, true), self::class);
        }

        if ($payment->status == "succeeded") {

            $transaction = \Yii::$app->db->beginTransaction();

            try {
                $shopPayment = new ShopPayment();
                $shopPayment->amount = $shopBill->amount;
                $shopPayment->currency_code = $shopBill->currency_code;
                $shopPayment->shop_pay_system_id = $shopBill->shop_pay_system_id;
                $shopPayment->shop_buyer_id = $shopBill->shop_buyer_id;
                $shopPayment->shop_order_id = $shopBill->shop_order_id;
                $shopPayment->external_id = $shopBill->external_id;
                $shopPayment->external_data = $shopBill->external_data;
                $shopPayment->external_name = $shopBill->external_name;
                $shopPayment->comment = $shopBill->description;

                if (!$shopPayment->save()) {
                    throw new Exception("Не сохранился платеж: " . print_r($shopPayment->errors, true));
                }

                $shopBill->shop_payment_id = $shopPayment->id;
                $shopBill->paid_at = time();

                if (!$shopBill->save()) {
                    throw new Exception("Не сохранился счет: " . print_r($shopBill->errors, true));
                }

                $shopBill->shopOrder->paid_at = time();
                $shopBill->shopOrder->save();

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                \Yii::error($e->getMessage(), self::class);
                throw $e;
            }
        }

        return "Ok";
    }
}