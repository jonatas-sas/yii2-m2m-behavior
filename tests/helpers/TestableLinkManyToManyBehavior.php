<?php

namespace odara\yii\tests\helpers;

use odara\yii\behaviors\LinkManyToManyBehavior;

/**
 * Testable version of LinkManyToManyBehavior
 * exposing protected methods for unit testing.
 *
 * @internal For testing purposes only.
 */
class TestableLinkManyToManyBehavior extends LinkManyToManyBehavior
{
    /**
     * Exposes the protected normalizePrimaryKey() method for testing.
     *
     * @param mixed $value
     *
     * @return int|string
     */
    public function normalizePrimaryKeyPublic($value)
    {
        return $this->normalizePrimaryKey($value);
    }
}
