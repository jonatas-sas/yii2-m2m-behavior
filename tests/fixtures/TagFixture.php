<?php

namespace odara\yii\tests\fixtures;

use yii\test\ActiveFixture;

/**
 * Fixture for the `tag` table.
 *
 * Provides initial records for the Tag model used in tests.
 */
class TagFixture extends ActiveFixture
{
    /**
     * @var string the model class associated with this fixture.
     */
    public $modelClass = \odara\yii\tests\models\Tag::class;

    /**
     * @var string the path to the data file.
     */
    public $dataFile = __DIR__ . '/data/tag.php';
}
