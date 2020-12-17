<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\shop\yookassa;

use skeeks\cms\shop\delivery\DeliveryHandler;
use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\paysystem\PaysystemHandler;
use skeeks\cms\shop\widgets\admin\SmartWeightInputWidget;
use skeeks\yii2\form\fields\FieldSet;
use skeeks\yii2\form\fields\NumberField;
use skeeks\yii2\form\fields\WidgetField;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class YookassaPaysystemHandler extends PaysystemHandler
{
    /**
     * @var string
     */
    public $shop_id = '';

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
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'shop_id'     => "Shop id",
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            //'shop_id' => "Этот город будет открыт на карте по умолчанию",
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
                    'shop_id',
                ],
            ],

        ];
    }
}