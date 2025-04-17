<?php

namespace odara\yii\tests\models;

use yii\db\ActiveRecord;

/**
 * Tag model used in many-to-many relationship with Item.
 */
class Tag extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'tag';
    }
}
