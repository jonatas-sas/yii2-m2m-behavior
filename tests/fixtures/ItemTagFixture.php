<?php

namespace odara\yii\tests\fixtures;

use yii\test\ActiveFixture;

/**
 * Fixture for the pivot table `item_tag`.
 *
 * Used to simulate many-to-many relationships between items and tags.
 */
class ItemTagFixture extends ActiveFixture
{
    /**
     * @var string the name of the pivot table.
     */
    public $tableName = 'item_tag';

    /**
     * @var string the path to the data file.
     */
    public $dataFile = __DIR__ . '/data/item_tag.php';
}
