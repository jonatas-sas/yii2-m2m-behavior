<?php

namespace odara\yii\behaviors;

use JsonException;
use yii\base\Behavior;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\db\ActiveQueryInterface;
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
 *             'categories' => [
 *                 'class'              => LinkManyToManyBehavior::class,
 *                 'relation'           => 'categories',
 *                 'referenceAttribute' => 'categoryIds',
 *                 'deleteOnUnlink'     => true, // default
 *                 'extraColumns'       => [
 *                     'created_at' => static fn() => time(),
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
 * - When `deleteOnUnlink` is `false`, junction rows will be left in place (not deleted).
 * - `extraColumns` allows you to add extra data to the junction (e.g., timestamps or flags). *
 *
 * @property      BaseActiveRecord                    $owner
 * @property      array<mixed>                        $referenceValue
 * @property      bool                                $isReferenceValueInitialized
 * @property-read array<ActiveRecordInterface>        $relatedRecords
 * @property-read class-string<ActiveRecordInterface> $relatedModelClass
 */
class LinkManyToManyBehavior extends Behavior
{
    /**
     * @var string|null the relation name defined in the owner model.
     */
    public ?string $relation = null;

    /**
     * @var string|null the virtual attribute used to assign related record IDs.
     */
    public ?string $referenceAttribute = null;

    /**
     * @var array<string, int|string|bool|float|callable> additional columns to be saved into
     * the junction table.
     */
    public array $extraColumns = [];

    /**
     * @var bool whether to delete junction table rows on unlink.
     */
    public bool $deleteOnUnlink = true;

    // SECTION: Initialization
    /**
     * @var array<mixed>|null the internal value for the reference attribute.
     */
    private ?array $referenceValueInternal = null;

    /**
     * @var class-string<ActiveRecordInterface>|null
     */
    private ?string $relatedModelClass = null;

    /**
     * @var string[] Ordered list of primary key fields for the related model.
     */
    private array $relatedPrimaryKeyFields = [];

    /**
     * @var bool Whether the reference was set manually via setter
     */
    private bool $referenceWasSetManually = false;

    /**
     * Attaches the behavior to the owner and performs internal validation and initialization.
     *
     * @param BaseActiveRecord $owner the component that this behavior is attached to
     *
     * @return void
     *
     * @throws InvalidArgumentException if required configuration is missing or invalid
     */
    public function attach($owner): void
    {
        parent::attach($owner);

        if (!$owner instanceof BaseActiveRecord) {
            throw new InvalidArgumentException(
                sprintf('Behavior must be attached to an instance of %s.', BaseActiveRecord::class)
            );
        }

        if (empty($this->relation)) {
            throw new InvalidArgumentException('The "relation" property must be defined.');
        }

        if (empty($this->referenceAttribute)) {
            throw new InvalidArgumentException('The "referenceAttribute" property must be defined.');
        }

        $relationGetter = 'get' . ucfirst($this->relation);

        if (!method_exists($owner, $relationGetter)) {
            throw new InvalidArgumentException(sprintf(
                'Relation method "%s()" does not exist on class %s.',
                $relationGetter,
                get_class($owner)
            ));
        }

        $query = $owner->$relationGetter();

        if (!$query instanceof ActiveQueryInterface) {
            throw new InvalidArgumentException(sprintf(
                'Relation "%s" must return an instance of %s.',
                $this->relation,
                ActiveQueryInterface::class
            ));
        }

        $relation = $owner->getRelation($this->relation);

        if (
            !isset($relation->modelClass) ||
            !is_string($relation->modelClass) ||
            !is_subclass_of($relation->modelClass, ActiveRecordInterface::class)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Relation "%s" must be a valid relation to a class implementing %s.',
                $this->relation,
                ActiveRecordInterface::class
            ));
        }

        $modelClass                    = $relation->modelClass;
        $this->relatedModelClass       = $modelClass;
        $this->relatedPrimaryKeyFields = (new $modelClass())->primaryKey();

        if ($this->isPrimaryKeyComposed()) {
            throw new InvalidArgumentException(sprintf(
                'Composite primary keys are not yet supported by %s. Model "%s" defines multiple PK fields: [%s].',
                static::class,
                $modelClass,
                implode(', ', $this->relatedPrimaryKeyFields)
            ));
        }

        $this->initReferenceValue();
    }

    /**
     * Initializes the reference value from related records or from provided primary keys.
     *
     * If $changedPrimaryKeys is not provided, related records are fetched from the relation.
     * The internal hash is updated only when the keys are computed internally.
     *
     * @param array<array<string, mixed>>|null $changedPrimaryKeys Optional pre-computed PKs.
     *
     * @return void
     */
    protected function initReferenceValue(?array $changedPrimaryKeys = null): void
    {
        $this->referenceWasSetManually = false;

        $primaryKeys = $changedPrimaryKeys;

        if ($primaryKeys === null) {
            $primaryKeys = $this->loadPrimaryKeys();

            $this->computeRelationHash($primaryKeys, true);
        }

        $this->referenceValueInternal = $primaryKeys;
    }

    /**
     * Loads and normalizes the primary keys from the related records.
     *
     * This method retrieves the currently populated related models via `getRelatedRecords()`
     * and converts each one to its full primary key array using `getPrimaryKey(true)`.
     * It returns the result as a list of associative arrays representing each key.
     *
     * This is useful for working consistently with both scalar and composite keys.
     *
     * @return array<array<string, string|int>> List of normalized primary keys.
     *
     * @throws InvalidArgumentException if any related record is invalid.
     */
    protected function loadPrimaryKeys(): array
    {
        /** @var array<array<string, string|int>> $records */
        $records = array_map(
            static fn (ActiveRecordInterface $record): array => (array)$record->getPrimaryKey(true),
            $this->getRelatedRecords()
        );

        return $records;
    }

    // SECTION: Getters
    /**
     * Returns the current reference value (list of primary keys for the relation).
     *
     * This value is normalized into either:
     * - A list of scalar values (e.g., `[1, 2, 3]`) for **single** primary key relations.
     * - A list of associative arrays (e.g., `[['id' => 1], ['id' => 2]]`) for **composite** keys.
     *
     * The return format is adjusted automatically depending on the key composition.
     *
     * @return array<array<string, mixed>|int|string> List of scalar or composite keys.
     *
     * @throws InvalidArgumentException If a primary key value is missing or has an invalid type.
     */
    public function getReferenceValue(): array
    {
        if (!$this->referenceWasSetManually && $this->isReferenceRelationDirty()) {
            $this->updateReferenceFromRelation();
        }

        /** @var string $field */
        $field  = reset($this->relatedPrimaryKeyFields);
        $result = [];

        /** @var array<array<string, mixed>> $reference */
        $reference = $this->referenceValueInternal;

        foreach ($reference as $index => $pk) {
            if (!is_array($pk) || !array_key_exists($field, $pk)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid reference format at index %d. Expected array with key "%s".',
                    $index,
                    $field
                ));
            }

            $result[] = $pk[$field];
        }

        /** @var array<int|string> $result */
        return $result;
    }

    /**
     * Returns the populated related records from the owner relation.
     *
     * If the relation is not yet populated, returns an empty array.
     *
     * @return array<ActiveRecordInterface>
     *
     * @throws InvalidArgumentException if relation records are invalid.
     */
    public function getRelatedRecords(): array
    {
        /** @var array<ActiveRecordInterface> $records */
        $records = $this->owner->{$this->relation};

        foreach ($records as $record) {
            if (!$record instanceof ActiveRecordInterface) {
                throw new InvalidArgumentException(sprintf(
                    'All records in relation "%s" must implement %s.',
                    $this->relation,
                    ActiveRecordInterface::class
                ));
            }
        }

        return array_values($records);
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

    // SECTION: Setters
    /**
     * Sets the reference value manually, allowing the assignment of related record identifiers.
     *
     * This value will later be used to synchronize the many-to-many relationship during save.
     *
     * Note: Composite primary keys are not yet supported by this behavior.
     * All primary keys must be scalar (single-column) values or ActiveRecord instances with scalar PKs.
     *
     * @param ActiveRecordInterface|array<int|string|array<string, mixed>|ActiveRecordInterface> $value
     *
     * @throws InvalidArgumentException If the value is not valid for the related PK structure.
     */
    public function setReferenceValue(ActiveRecordInterface|array $value): void
    {
        $this->referenceWasSetManually = true;

        $fields = $this->relatedPrimaryKeyFields;

        if ($value instanceof ActiveRecordInterface) {
            $value = [$value];
        }

        /** @var string $field */
        $field = reset($fields);

        $normalized = array_map(
            static fn ($item): array => [
                $field => $item instanceof ActiveRecordInterface ? $item->getPrimaryKey() : $item,
            ],
            $value
        );

        $this->referenceValueInternal = $normalized;
    }

    // SECTION: Extra Columns
    /**
     * Resolves extra columns for the junction table, handling callables.
     *
     * @param ActiveRecordInterface|null $model
     *
     * @return array<string, mixed>
     */
    protected function composeExtraColumns(?ActiveRecordInterface $model = null): array
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

    // SECTION: Events
    /**
     * Declares events handled by this behavior.
     *
     * @return array<string, string>
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
     * @return void
     *
     * @throws Exception|StaleObjectException
     */
    public function afterSave($event): void
    {
        if (!$this->getIsReferenceValueInitialized()) {
            return;
        }

        $relatedClass = $this->relatedModelClass;
        $referenceMap = $this->collectReferenceMap();

        [$unlinkModels, $linkModels] = $this->resolveSyncModels($referenceMap, $relatedClass);

        $this->applyLinkChanges($unlinkModels, $linkModels);

        $this->referenceWasSetManually = false;
    }

    /**
     * Unlinks all models after delete.
     *
     * @param Event $event
     *
     * @return void
     */
    public function afterDelete($event): void
    {
        $this->owner->unlinkAll((string)$this->relation, $this->deleteOnUnlink);
    }

    // SECTION: Verifications
    /**
     * Determines whether the related model defines a composite (multi-column) primary key.
     *
     * This method checks if the related model (defined in the relation) has more than one primary key column.
     * Although composite keys are not currently supported by this behavior, this method remains available
     * for internal diagnostics and future compatibility checks.
     *
     * @return bool Whether the related model has a composite primary key.
     */
    public function isPrimaryKeyComposed(): bool
    {
        return count($this->relatedPrimaryKeyFields) > 1;
    }

    /**
     * Returns true if the relation and internal reference hash do not match.
     *
     * @return bool
     */
    public function isReferenceRelationDirty(): bool
    {
        if ($this->referenceValueInternal === null) {
            return true;
        }

        [$changed] = $this->computeRelationHash();

        return $changed;
    }

    /**
     * Returns whether the reference value was manually overridden.
     *
     * @return bool
     */
    public function isReferenceManualOverride(): bool
    {
        return $this->referenceWasSetManually;
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

    // SECTION: Synchronization State
    /**
     * @var string|null Cached hash of the relation at attach-time.
     */
    private ?string $relationHash = null;

    /**
     * Computes the hash of the relation using its primary keys and updates internal state if changed.
     *
     * This method compares the current relation state (as primary keys) with the previously known hash.
     * Optionally, it receives already normalized primary keys and skips re-sync logic during init.
     *
     * @param array<array<string, int|string>> $relatedPrimaryKeys Optional list of normalized PKs.
     * @param bool $fromInit Whether the call comes from initial attach/init (disables internal sync).
     *
     * @return array{bool, string, string} Array with [changed, newHash, oldHash] in this order.
     *
     * @throws JsonException
     */
    protected function computeRelationHash(array $relatedPrimaryKeys = [], bool $fromInit = false): array
    {
        $hasPrimaryKeys = !empty($relatedPrimaryKeys);
        $primaryKeys    = $relatedPrimaryKeys;
        $oldHash        = (string)$this->relationHash;
        $newHash        = '';
        $changed        = false;

        if (!$hasPrimaryKeys && !$this->owner->isRelationPopulated((string)$this->relation)) {
            if (!empty($oldHash)) {
                $this->relationHash = null;

                $changed = true;
            }

            return [$changed, '', $oldHash];
        }

        if (!$hasPrimaryKeys) {
            $primaryKeys = $this->loadPrimaryKeys();
        }

        $newHash = md5((string)json_encode(
            $primaryKeys,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));

        if ($oldHash !== $newHash) {
            $this->relationHash = $newHash;

            $changed = true;
        }

        if (!$fromInit) {
            $this->initReferenceValue($primaryKeys);
        }

        return [$changed, $newHash, $oldHash];
    }

    /**
     * Resets the manual set flag, enabling automatic reference updates again.
     *
     * @return void
     */
    public function resetReferenceManualOverride(): void
    {
        $this->referenceWasSetManually = false;
    }

    /**
     * Updates the internal reference value based on the current relation.
     *
     * This method should be called if you detect that the relation was modified externally
     * (e.g., via `populateRelation()`, `with()`, or `link()`).
     *
     * @return void
     */
    public function updateReferenceFromRelation(): void
    {
        $this->initReferenceValue();
    }

    // SECTION: Helpers
    /**
     * Builds a unique string key from a primary key array,
     * preserving positional order based on expected PK fields.
     *
     * @param array<string, mixed> $primaryKey Associative array of PK values.
     * @param string[] $orderedFields Fields in expected PK order (e.g., from primaryKey()).
     *
     * @return string
     *
     * @throws InvalidArgumentException if any expected field is missing from the array.
     */
    protected function buildReferenceKey(array $primaryKey, array $orderedFields): string
    {
        $values = [];

        foreach ($orderedFields as $field) {
            if (!array_key_exists($field, $primaryKey)) {
                throw new InvalidArgumentException("Missing primary key field '{$field}' when building reference key.");
            }

            $values[] = $primaryKey[$field];
        }

        return implode('-', $values);
    }

    /**
     * Builds a map of reference keys from the current reference value.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function collectReferenceMap(): array
    {
        $pkFields = $this->relatedPrimaryKeyFields;
        $map      = [];

        /** @var array<array<string, mixed>> $reference */
        $reference = $this->referenceValueInternal;

        foreach ($reference as $ref) {
            /** @var array<string, mixed> $ref */
            $ref       = (array)$ref;
            $key       = $this->buildReferenceKey($ref, $pkFields);
            $map[$key] = $ref;
        }

        return $map;
    }

    /**
     * Determines which models need to be unlinked or linked.
     *
     * @param array<string, array<string, mixed>> $referenceMap
     * @param class-string<ActiveRecordInterface> $relatedClass
     *
     * @return array{list<ActiveRecordInterface>, list<ActiveRecordInterface>}
     */
    protected function resolveSyncModels(
        array $referenceMap,
        string $relatedClass
    ): array {
        $pkFields       = $this->relatedPrimaryKeyFields;
        $relatedRecords = $this->getRelatedRecords();

        $existingMap = [];

        foreach ($relatedRecords as $model) {
            /** @var array<string, mixed> $pk */
            $pk  = (array)$model->getPrimaryKey(true);
            $key = $this->buildReferenceKey($pk, $pkFields);

            $existingMap[$key] = $model;
        }

        $unlinkKeys   = array_diff(array_keys($existingMap), array_keys($referenceMap));
        $unlinkModels = array_values(array_intersect_key($existingMap, array_flip($unlinkKeys)));
        $linkKeys     = array_diff(array_keys($referenceMap), array_keys($existingMap));
        $linkModels   = [];

        if (!empty($linkKeys)) {
            $pkValues = array_map(
                static fn (string $key): array => $referenceMap[$key],
                $linkKeys
            );

            /** @var list<ActiveRecordInterface> $linkModels */
            $linkModels = $relatedClass::findAll($pkValues);
        }

        return [
            $unlinkModels,
            array_values($linkModels),
        ];
    }

    /**
     * Applies unlink and link operations to synchronize the relation.
     *
     * @param array<ActiveRecordInterface> $unlinkModels
     * @param array<ActiveRecordInterface> $linkModels
     *
     * @return void
     */
    protected function applyLinkChanges(
        array $unlinkModels,
        array $linkModels
    ): void {
        foreach ($unlinkModels as $model) {
            $this->owner->unlink((string)$this->relation, $model, $this->deleteOnUnlink);
        }

        foreach ($linkModels as $model) {
            $this->owner->link((string)$this->relation, $model, $this->composeExtraColumns($model));
        }
    }

    // SECTION: Magic Methods
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
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if ($name === $this->referenceAttribute) {
            if (!$value instanceof ActiveRecordInterface && !is_array($value)) {
                $message = sprintf(
                    'Invalid reference value for "%s". Expected an array or instance of %s.',
                    $name,
                    ActiveRecordInterface::class
                );

                throw new InvalidArgumentException($message);
            }
            /** @var ActiveRecordInterface|array<int|string|array<string, mixed>|ActiveRecordInterface> $value */
            $this->setReferenceValue($value);
        } else {
            parent::__set($name, $value);
        }
    }
}
