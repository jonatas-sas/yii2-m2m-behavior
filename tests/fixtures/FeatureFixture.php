<?php

namespace odara\yii\tests\fixtures;

use odara\yii\tests\models\Feature;
use yii\test\ActiveFixture;

/**
 * Fixture for the `feature` table.
 *
 * Provides initial records for the Feature model used in tests.
 */
class FeatureFixture extends ActiveFixture
{
    /**
     * @var string the model class associated with this fixture.
     */
    public $modelClass = Feature::class;

    /**
     * @var string the path to the data file.
     */
    public $dataFile = __DIR__ . '/data/feature.php';
}
