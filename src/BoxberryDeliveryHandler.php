<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\boxberry;

use skeeks\cms\shop\delivery\DeliveryHandler;
use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\widgets\admin\SmartWeightInputWidget;
use skeeks\yii2\form\fields\FieldSet;
use skeeks\yii2\form\fields\NumberField;
use skeeks\yii2\form\fields\WidgetField;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class BoxberryDeliveryHandler extends DeliveryHandler
{
    /**
     * @var string
     */
    public $api_key = '';
    public $custom_city = 'Москва';
    public $weight = 1000;

    public $height = 20;
    public $width = 20;
    public $depth = 20;

    /**
     * @var string
     */
    public $checkoutModelClass = BoxberryCheckoutModel::class;

    /**
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name' => \Yii::t('skeeks/shop/app', 'Boxberry'),
        ]);
    }


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['api_key'], 'required'],
            [['custom_city'], 'string'],
            [['api_key'], 'string'],

            [['weight'], 'integer'],

            [['height'], 'integer'],
            [['width'], 'integer'],
            [['depth'], 'integer'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'api_key'     => "Ключ api",
            'custom_city' => "Город",

            'weight' => "Вес заказа",

            'height' => "Высота коробки заказа",
            'width'  => "Ширина коробки заказа",
            'depth'  => "Глубина коробки заказа",
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'custom_city' => "Этот город будет открыт на карте по умолчанию",
        ]);
    }


    /**
     * @return array
     */
    public function getConfigFormFields()
    {
        return [
            'main'    => [
                'class'  => FieldSet::class,
                'name'   => 'Основные',
                'fields' => [
                    'api_key',
                ],
            ],
            'default' => [
                'class'  => FieldSet::class,
                'name'   => 'Данные по умолчанию',
                'fields' => [
                    'custom_city',

                    'weight' => [
                        'class' => WidgetField::class,
                        'widgetClass' => SmartWeightInputWidget::class
                    ],

                    'height' => [
                        'class' => NumberField::class,
                        'append' => 'см.'
                    ],

                    'width' => [
                        'class' => NumberField::class,
                        'append' => 'см.'
                    ],

                    'depth' => [
                        'class' => NumberField::class,
                        'append' => 'см.'
                    ]
                ],
            ],
        ];
    }


    /**
     * @param ActiveForm $activeForm
     * @return string
     */
    public function renderCheckoutForm(ActiveForm $activeForm, ShopOrder $shopOrder)
    {
        \Yii::$app->view->registerJsFile("//points.boxberry.ru/js/boxberry.js");
        $apiKey = $this->api_key;
        $custom_city = $this->custom_city;

        $weight = $shopOrder->weight ? $shopOrder->weight : 1000;
        $money = (float)$shopOrder->money->amount;

        \Yii::$app->view->registerJs(<<<JS

$("#sx-boxberry-open").on("click", function() {
    boxberry.open(callback_function, '1$8f7ca7918b542c67a6d08d6ee72f8296', '{$custom_city}', '', {$money}, {$weight}, 0 , 20, 20, 20); return false;
});

function callback_function(result){
    
    var data = JSON.stringify(result);
    $("#shoporder-delivery_handler_data_jsoned").empty().append(data).change();
    /*if (result.prepaid=='1') {
        alert('Отделение работает только по предоплате!');
    }*/
}
JS
        );

        $result = '<div style="display: none;">';
        $result .= $activeForm->field($shopOrder->deliveryHandlerCheckoutModel, "id");
        $result .= '</div>';

        if ($shopOrder->deliveryHandlerCheckoutModel->id) {
            $result .= <<<HTML
            <div>Адрес: {$shopOrder->deliveryHandlerCheckoutModel->address}</div>
            <div>Телефон: {$shopOrder->deliveryHandlerCheckoutModel->phone}</div>
            <div>Время работы: {$shopOrder->deliveryHandlerCheckoutModel->workschedule}</div>
            <a href="#" class="sx-dashed" id="sx-boxberry-open">Изменить пункт выдачи Boxberry</a>
HTML;
        } else {
            $result .= <<<HTML
            <a href="#" class="sx-dashed" id="sx-boxberry-open" style="color: red;">Выбрать пункт выдачи Boxberry</a>
HTML;
        }


        return $result;
    }

}