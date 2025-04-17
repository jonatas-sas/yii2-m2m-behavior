<?php

namespace odara\yii\tests\models;

use yii\db\ActiveRecord;

/**
 * Tag model used in many-to-many relationship with Item.
 *
 * @property int    $id
 * @property string $name
 */
class Category extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'category';
    }
}
