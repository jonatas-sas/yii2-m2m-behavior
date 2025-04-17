<?php

namespace odara\yii\behaviors;

use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecordInterface;
use yii\db\BaseActiveRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;

/**
 * LinkManyToManyBehavior provides support for ActiveRecord many-to-many relation saving.
 *
 * This behavior synchronizes a many-to-many relation with a virtual attribute.
 * You can use it to assign related records by their primary keys (e.g., in forms).
 *
 * Example:
 *
 * ```php
 * class Item extends \yii\db\ActiveRecord
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'm2m' => [
 *                 'class'              => LinkManyToManyBehavior::class,
 *                 'relation'           => 'categories',
 *                 'referenceAttribute' => 'categoryIds',
 *                 'deleteOnUnlink'     => true, // default
 *                 'extraColumns'       => [
 *                     'created_at' => fn() => time(),
 *                     'type'       => 'manual',
 *                 ],
 *             ],
 *         ];
 *     }
 *
 *     public function getCategories()
 *     {
 *         return $this->hasMany(Category::class, ['id' => 'category_id'])
 *             ->viaTable('item_category', ['item_id' => 'id']);
 *     }
 * }
 * ```
 *
 * Notes:
 * - The `categoryIds` attribute is **virtual**, enabled automatically by the behavior.
 * - Ensure `getCategories()` is declared and matches the relation name.
 * - When `deleteOnUnlink` is `false`, pivot rows will be left in place (not deleted).
 * - `extraColumns` allows you to add extra data to the pivot (e.g., timestamps or flags). *
 *
 * @property BaseActiveRecord $owner
 * @property array|null       $referenceValue
 * @property bool             $isReferenceValueInitialized
 */
class LinkManyToManyBehavior extends Behavior
{
    /**
     * @var string the relation name defined in the owner model.
     */
    public $relation;

    /**
     * @var string|null the virtual attribute used to assign related record IDs.
     */
    public $referenceAttribute;

    /**
     * @var array additional columns to be saved into the junction table.
     */
    public $extraColumns = [];

    /**
     * @var bool whether to delete junction table rows on unlink.
     */
    public $deleteOnUnlink = true;

    /**
     * @var array|null the internal value for the reference attribute.
     */
    private $referenceValueInternal;

    /**
     * Sets the reference value explicitly.
     *
     * @param mixed $value
     *
     * @return void
     */
    public function setReferenceValue($value)
    {
        $this->referenceValueInternal = $value;
    }

    /**
     * Gets the reference value, initializing it if not set.
     *
     * @return array
     */
    public function getReferenceValue()
    {
        if ($this->referenceValueInternal === null) {
            $this->referenceValueInternal = $this->initReferenceValue();
        }

        return $this->referenceValueInternal;
    }

    /**
     * Checks whether the reference value has been initialized.
     *
     * @return bool
     */
    public function getIsReferenceValueInitialized(): bool
    {
        return $this->referenceValueInternal !== null;
    }

    /**
     * Initializes the reference value from the current relation state.
     *
     * @return array
     */
    protected function initReferenceValue()
    {
        $result         = [];
        $relatedRecords = $this->owner->{$this->relation};

        foreach ((array)$relatedRecords as $relatedRecord) {
            $result[] = $this->normalizePrimaryKey($relatedRecord->getPrimaryKey());
        }

        return $result;
    }

    /**
     * Normalizes a primary key for comparison.
     *
     * @param mixed $primaryKey
     *
     * @return string|int
     */
    protected function normalizePrimaryKey($primaryKey)
    {
        if (is_object($primaryKey) && method_exists($primaryKey, '__toString')) {
            return (string)$primaryKey;
        }

        return $primaryKey;
    }

    /**
     * Enables magic getter for the virtual reference attribute.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === $this->referenceAttribute) {
            return $this->getReferenceValue();
        }

        return parent::__get($name);
    }

    /**
     * Enables magic setter for the virtual reference attribute.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if ($name === $this->referenceAttribute) {
            $this->setReferenceValue($value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Supports dynamic property getter.
     *
     * @return bool
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return parent::canGetProperty($name, $checkVars) || $name === $this->referenceAttribute;
    }

    /**
     * Supports dynamic property setter.
     *
     * @return bool
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return parent::canSetProperty($name, $checkVars) || $name === $this->referenceAttribute;
    }

    /**
     * Declares events handled by this behavior.
     *
     * @return array
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * Syncs the relation after save.
     *
     * @param Event $event
     *
     * @throws Exception|StaleObjectException
     */
    public function afterSave($event)
    {
        if (!$this->getIsReferenceValueInitialized()) {
            return;
        }

        $linkModels    = [];
        $unlinkModels  = [];
        $newReferences = $this->getReferenceValue();

        if (!is_array($newReferences)) {
            $newReferences = empty($newReferences) ? [] : [$newReferences];
        }

        $newReferences = array_unique($newReferences);

        foreach ($this->owner->{$this->relation} as $relatedModel) {
            $primaryKey = $this->normalizePrimaryKey($relatedModel->getPrimaryKey());

            if (($index = array_search($primaryKey, $newReferences, true)) !== false) {
                unset($newReferences[$index]);
            } else {
                $unlinkModels[] = $relatedModel;
            }
        }

        if (!empty($newReferences)) {
            $relatedClass = $this->owner->getRelation($this->relation)->modelClass;
            $linkModels = $relatedClass::findAll(array_values($newReferences));
        }

        foreach ($unlinkModels as $model) {
            $this->owner->unlink($this->relation, $model, $this->deleteOnUnlink);
        }

        foreach ($linkModels as $model) {
            $this->owner->link($this->relation, $model, $this->composeExtraColumns($model));
        }
    }

    /**
     * Unlinks all models after delete.
     *
     * @param Event $event
     *
     * @return void
     */
    public function afterDelete($event)
    {
        $this->owner->unlinkAll($this->relation, $this->deleteOnUnlink);
    }

    /**
     * Resolves extra columns for the junction table, handling callables.
     *
     * @param ActiveRecordInterface|null $model
     *
     * @return array
     */
    protected function composeExtraColumns($model = null): array
    {
        if (empty($this->extraColumns)) {
            return [];
        }

        $resolved = [];

        foreach ($this->extraColumns as $column => $value) {
            $resolved[$column] = is_callable($value) ? $value($model) : $value;
        }

        return $resolved;
    }
}
