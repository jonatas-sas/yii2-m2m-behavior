<?php

namespace odara\yii\tests\fixtures;

use odara\yii\tests\models\Category;
use yii\test\ActiveFixture;

/**
 * Fixture for the `category` table.
 *
 * Provides initial records for the Category model used in tests.
 */
class CategoryFixture extends ActiveFixture
{
    /**
     * @var string the model class associated with this fixture.
     */
    public $modelClass = Category::class;

    /**
     * @var string the path to the data file.
     */
    public $dataFile = __DIR__ . '/data/category.php';
}
