<?php

namespace odara\yii\tests\models;

use yii\db\ActiveRecord;

/**
 * Tag model used in many-to-many relationship with Item.
 *
 * @property int   $id
 * @property string $name
 */
class Tag extends ActiveRecord
{
    /**
     * @inheritdoc
     *
     * @return string
     */
    public static function tableName()
    {
        return 'tag';
    }
}
