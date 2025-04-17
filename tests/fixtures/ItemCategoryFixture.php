<?php

namespace odara\yii\tests\fixtures;

use yii\test\ActiveFixture;

/**
 * Fixture for the pivot table `item_category`.
 *
 * Used to simulate many-to-many relationships between items and categories.
 */
class ItemCategoryFixture extends ActiveFixture
{
    /**
     * @var string the name of the pivot table.
     */
    public $tableName = 'item_category';

    /**
     * @var string the path to the data file.
     */
    public $dataFile = __DIR__ . '/data/item_category.php';
}
