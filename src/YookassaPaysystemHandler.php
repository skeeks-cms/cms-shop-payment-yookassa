<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\yookassa;

use skeeks\cms\helpers\StringHelper;
use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\models\ShopPayment;
use skeeks\cms\shop\paysystem\PaysystemHandler;
use skeeks\yii2\form\fields\BoolField;
use skeeks\yii2\form\fields\FieldSet;
use skeeks\yii2\form\fields\NumberField;
use skeeks\yii2\form\fields\SelectField;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use YooKassa\Client;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class YookassaPaysystemHandler extends PaysystemHandler
{
    /**
     * @var integer
     */
    public $shop_id = '';

    /**
     * @var string
     */
    public $secret_key = '';

    /**
     * @var string
     */
    public $tax_system_code = '';

    /**
     * @var bool Отправлять данные по чекам?
     */
    public $is_receipt = false;

    /**
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name' => "Yookassa",
        ]);
    }


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['shop_id'], 'required'],
            [['shop_id'], 'integer'],
            [['secret_key'], 'required'],
            [['secret_key'], 'string'],
            [['tax_system_code'], 'string'],
            [['is_receipt'], 'boolean'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'shop_id'    => "ID магазина",
            'secret_key' => "Секретный ключ",
            'tax_system_code' => "Система налогообложения магазина",
            'is_receipt' => "Отправлять данные для формирования чеков?",
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'shop_id'    => "shop_id",
            'secret_key' => "secret_key",
            'tax_system_code' => "Используется при формировании чека",
            'is_receipt' => "Необходимо передавать, если вы отправляете данные для формирования чеков по одному из сценариев: Платеж и чек одновременно или Сначала чек, потом платеж.",
        ]);
    }


    /**
     * @return array
     */
    public function getConfigFormFields()
    {
        return [
            'main' => [
                'class'  => FieldSet::class,
                'name'   => 'Основные',
                'fields' => [
                    'shop_id'    => [
                        'class' => NumberField::class,
                    ],
                    'secret_key',
                    'is_receipt' => [
                        'class'     => BoolField::class,
                        'allowNull' => false,
                    ],
                    'tax_system_code' => [
                        'class'     => SelectField::class,
                        'items' => [
                            '' => "Не выбрана... берется из настроек магазина",
                            '1' => "Общая система налогообложения",
                            '2' => "Упрощенная (УСН, доходы)",
                            '3' => "Упрощенная (УСН, доходы минус расходы)",
                            '4' => "Единый налог на вмененный доход (ЕНВД)",
                            '5' => "Единый сельскохозяйственный налог (ЕСН)",
                            '6' => "Патентная система налогообложения",
                        ],
                    ],
                ],
            ],

        ];
    }

    /**
     * @param ShopPayment $shopPayment
     * @return \yii\console\Response|\yii\web\Response
     */
    public function actionPayOrder(ShopOrder $shopOrder)
    {
        $model = $this->getShopBill($shopOrder);

        $yooKassa = $model->shopPaySystem->handler;
        $money = $model->money->convertToCurrency("RUB");
        $returnUrl = $shopOrder->getUrl([], true);

        /**
         * Для чеков нужно указывать информацию о товарах
         * https://yookassa.ru/developers/api?lang=php#create_payment
         */
        $contact_phone = trim($shopOrder->contact_phone);
        $contact_email = trim($shopOrder->contact_email);

        $contact_name = $shopOrder->contact_first_name . " " . $shopOrder->contact_last_name;
        $receipt = [];
        if ($yooKassa->is_receipt) {
            if ($this->tax_system_code) {
                $receipt['tax_system_code'] = (int) $this->tax_system_code;
            }

            $receipt['customer'] = [
                'full_name' => trim($contact_name),
            ];

            if ($contact_email) {
                $receipt['customer']['email'] = $contact_email;
            }
            if ($contact_phone) {
                $receipt['customer']['phone'] = $contact_phone;
            }

            foreach ($shopOrder->shopOrderItems as $shopOrderItem) {
                $itemData = [];

                $itemData['description'] = StringHelper::substr($shopOrderItem->name, 0, 128);
                $itemData['quantity'] = (float)$shopOrderItem->quantity;
                $itemData['vat_code'] = 1; //todo: доработать этот момент
                $itemData['payment_mode'] = "full_payment"; //todo: доработать этот момент
                $itemData['payment_subject'] = "commodity"; //todo: доработать этот момент
                $itemData['amount'] = [
                    'value'    => $shopOrderItem->money->amount,
                    'currency' => 'RUB',
                ];

                $receipt['items'][] = $itemData;
            }

            /**
             * Стоимость доставки так же нужно добавить
             */
            if ((float)$shopOrder->moneyDelivery->amount > 0) {
                $itemData = [];
                $itemData['description'] = StringHelper::substr($shopOrder->shopDelivery->name, 0, 128);
                $itemData['quantity'] = 1;
                $itemData['vat_code'] = 1; //todo: доработать этот момент
                $itemData['payment_mode'] = "full_payment"; //todo: доработать этот момент
                $itemData['payment_subject'] = "service"; //todo: доработать этот момент
                $itemData['amount'] = [
                    'value'    => $shopOrder->moneyDelivery->amount,
                    'currency' => 'RUB',
                ];

                $receipt['items'][] = $itemData;
            }

            $totalCalcAmount = 0;
            foreach ($receipt['items'] as $itemData) {
                $totalCalcAmount = $totalCalcAmount + ($itemData['amount']['value'] * $itemData['quantity']);
            }

            $discount = 0;
            if ($totalCalcAmount > (float)$money->amount) {
                $discount = abs((float)$money->amount - $totalCalcAmount);
            }

            /**
             * Стоимость скидки
             */
            //todo: тут можно еще подумать, это временное решение
            if ($discount > 0) {
                $discountValue = $discount;
                foreach ($receipt['items'] as $key => $item) {
                    if ($discountValue == 0) {
                        break;
                    }
                    if ($item['amount']['value']) {
                        if ($item['amount']['value'] >= $discountValue) {
                            $item['amount']['value'] = $item['amount']['value'] - $discountValue;
                            $discountValue = 0;
                        } else {
                            $item['amount']['value'] = 0;
                            $discountValue = $discountValue - $item['amount']['value'];
                        }
                    }

                    $receipt['items'][$key] = $item;
                }

                //$receipt['items'][] = $itemData;
            }
        }


        $client = new Client();
        $client->setAuth($yooKassa->shop_id, $yooKassa->secret_key);
        $payment = $client->createPayment([
            'receipt'      => $receipt,
            'amount'       => [
                'value'    => $money->amount,
                'currency' => 'RUB',
            ],
            //'capture' => true,
            'confirmation' => [
                'type'       => 'redirect',
                'return_url' => $returnUrl,
            ],
            'description'  => 'Заказ №' . $shopOrder->id,
        ],
            uniqid('', true)
        );

        \Yii::info(print_r($payment, true), self::class);

        if (!$payment->id) {
            \Yii::error('Yandex kassa payment id not found', self::class);
            throw new Exception('Yandex kassa payment id not found');
        }

        $model->external_id = $payment->id;
        $model->external_data = [
            'id'           => $payment->id,
            'status'       => $payment->status,
            'created_at'   => $payment->created_at,
            'confirmation' => [
                'type'  => $payment->confirmation->type,
                'url'   => $payment->confirmation->getConfirmationUrl(),
            ],
        ];

        if (!$model->save()) {
            \Yii::error("Не удалось сохранить платеж: ".print_r($model->errors, true), self::class);
            throw new Exception("Не удалось сохранить платеж: ".print_r($model->errors, true));
        }

        return \Yii::$app->response->redirect($payment->confirmation->getConfirmationUrl());
    }
}