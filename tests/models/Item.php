<?php

namespace odara\yii\tests\models;

use odara\yii\behaviors\LinkManyToManyBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Item model for testing LinkManyToManyBehavior.
 *
 * Represents an entity that is linked to multiple tags via a pivot table.
 *
 * @property int    $id
 * @property string $name
 * @property array  $tagIds
 * @property Tag[]  $tags
 */
class Item extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'item';
    }

    /**
     * Declares the many-to-many behavior to manage tag relations.
     *
     * @return array
     */
    public function behaviors()
    {
        return [
            'm2m' => [
                'class'              => LinkManyToManyBehavior::class,
                'relation'           => 'tags',
                'referenceAttribute' => 'tagIds',
            ],
        ];
    }

    /**
     * Defines the relation to Tag via the item_tag pivot table.
     *
     * @return ActiveQuery
     */
    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('item_tag', ['item_id' => 'id']);
    }
}
