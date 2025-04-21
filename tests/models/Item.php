<?php

namespace odara\yii\tests\models;

use odara\yii\behaviors\LinkManyToManyBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Item model for testing LinkManyToManyBehavior.
 *
 * Represents an entity that is linked to multiple tags via a junction table.
 *
 * @property int                  $id
 * @property string               $name
 * @property array<mixed>         $tagIds
 * @property array<mixed>         $categoryIds
 * @property-read array<Tag>      $tags
 * @property-read array<Category> $categories
 */
class Item extends ActiveRecord
{
    /**
     * @inheritdoc
     *
     * @return string
     */
    public static function tableName()
    {
        return 'item';
    }

    /**
     * Declares the many-to-many behavior to manage tag relations.
     *
     * @return array<array<mixed>>
     */
    public function behaviors()
    {
        return [
            'tags' => [
                'class'              => LinkManyToManyBehavior::class,
                'relation'           => 'tags',
                'referenceAttribute' => 'tagIds',
            ],

            'categories' => [
                'class'              => LinkManyToManyBehavior::class,
                'relation'           => 'categories',
                'referenceAttribute' => 'categoryIds',
                'deleteOnUnlink'     => true,
            ],
        ];
    }

    /**
     * Defines the relation to Tag via the item_tag junction table.
     *
     * @return ActiveQuery
     */
    public function getTags(): ActiveQuery
    {
        return $this
            ->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('item_tag', ['item_id' => 'id']);
    }

    /**
     * Defines the relation to Category via the item_category junction table.
     *
     * @return ActiveQuery
     */
    public function getCategories(): ActiveQuery
    {
        return $this
            ->hasMany(Category::class, ['id' => 'category_id'])
            ->viaTable('item_category', ['item_id' => 'id']);
    }
}
