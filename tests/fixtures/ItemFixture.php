<?php

namespace odara\yii\tests\fixtures;

use yii\test\ActiveFixture;

/**
 * Fixture for the `item` table.
 *
 * Provides initial records for the Item model used in tests.
 */
class ItemFixture extends ActiveFixture
{
    /**
     * @var string the model class associated with this fixture.
     */
    public $modelClass = \odara\yii\tests\models\Item::class;

    /**
     * @var string the path to the data file.
     */
    public $dataFile = __DIR__ . '/data/item.php';
}
