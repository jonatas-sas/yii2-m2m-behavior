<?php

namespace odara\yii\tests;

use odara\yii\behaviors\LinkManyToManyBehavior;
use odara\yii\tests\fixtures\CategoryFixture;
use odara\yii\tests\fixtures\ItemCategoryFixture;
use odara\yii\tests\fixtures\ItemFixture;
use odara\yii\tests\fixtures\ItemTagFixture;
use odara\yii\tests\fixtures\TagFixture;
use odara\yii\tests\models\Item;
use odara\yii\tests\models\Tag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Yii;
use yii\base\Component;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\base\UnknownPropertyException;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\ActiveRecordInterface;
use yii\db\Query;
use yii\test\FixtureTrait;

/**
 * Functional tests for LinkManyToManyBehavior using real database.
 */
class LinkManyToManyBehaviorTest extends TestCase
{
    use FixtureTrait;

    /**
     * Loads the database fixtures.
     *
     * @return array<string, string>
     */
    public function fixtures()
    {
        return [
            'items'         => ItemFixture::class,
            'tags'          => TagFixture::class,
            'categories'    => CategoryFixture::class,
            'item_tag'      => ItemTagFixture::class,
            'item_category' => ItemCategoryFixture::class,
        ];
    }

    /**
     * Prepares the test environment before each test.
     *
     * - Ensures the required database tables exist.
     * - Loads the fixtures to populate the test data.
     *
     * This method is automatically called by PHPUnit before each test method.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestTables();
        $this->loadFixtures();
    }

    /**
     * Cleans up the test environment after each test.
     *
     * Drops all tables related to the test to ensure the database is reset
     * for the next test run. This is important when using SQLite in memory,
     * since the schema persists across test methods.
     *
     * This method is automatically called by PHPUnit after each test method.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $db = Yii::$app->db;

        $db->createCommand()->dropTable('item_tag')->execute();
        $db->createCommand()->dropTable('item_category')->execute();
        $db->createCommand()->dropTable('tag')->execute();
        $db->createCommand()->dropTable('category')->execute();
        $db->createCommand()->dropTable('item')->execute();
    }

    // SECTION: Attach Method Validation
    /**
     * It should throw if the behavior is attached to a non-ActiveRecord object.
     *
     * Ensures the behavior is only applied to ActiveRecord-based models, preventing misuse.
     */
    public function testAttachFailsIfOwnerIsNotActiveRecord(): void
    {
        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Behavior must be attached to an instance of yii\db\BaseActiveRecord.');

        $target = new Component();

        //@phpstan-ignore-next-line
        $behavior->attach($target);
    }

    /**
     * It should throw if the "relation" property is not set.
     *
     * Validates that the relation name must be explicitly defined for behavior to function.
     */
    public function testAttachFailsIfRelationIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "relation" property must be defined.');

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'referenceAttribute' => 'tagIds',
        ]);

        $item->attachBehavior('tags', $behavior);
    }

    /**
     * It should throw if the "referenceAttribute" property is not set.
     *
     * Ensures that a valid reference attribute is defined for virtual field support.
     */
    public function testAttachFailsIfReferenceAttributeIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "referenceAttribute" property must be defined.');

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation' => 'tags',
        ]);

        $item->attachBehavior('tags', $behavior);
    }

    /**
     * It should throw if the relation method (getter) does not exist on the model.
     *
     * Confirms that the behavior validates method presence like `getTags()` correctly.
     */
    public function testAttachFailsIfRelationGetterDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relation method "getInvalidRelation()" does not exist on class');

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'invalidRelation',
            'referenceAttribute' => 'invalidIds',
        ]);

        $item->attachBehavior('invalid', $behavior);
    }

    /**
     * It should throw if the relation method does not return an ActiveQueryInterface.
     *
     * Verifies that relation methods must return a valid query object.
     */
    public function testAttachFailsIfRelationDoesNotReturnActiveQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Relation "elements" must return an instance of yii\db\ActiveQueryInterface.');

        $item = new class extends Item {
            public function getElements(): string
            {
                return 'not a query';
            }
        };

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'elements',
            'referenceAttribute' => 'elementsIds',
        ]);

        $item->attachBehavior('elements', $behavior);
    }

    /**
     * It should throw if the relation does not return a valid ActiveRecordInterface model class.
     */
    public function testAttachFailsIfRelationModelClassIsInvalid(): void
    {
        $msg = 'Relation "invalid" must be a valid relation to a class implementing yii\db\ActiveRecordInterface.';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($msg);

        $item = new class extends Item {
            public function getInvalid(): ActiveQuery
            {
                $query = Yii::createObject(ActiveQuery::class, [new stdClass()]);

                $query->modelClass = stdClass::class;

                return $query;
            }
        };

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'invalid',
            'referenceAttribute' => 'invalidIds',
        ]);

        $item->attachBehavior('invalid', $behavior);
    }

    // SECTION: Getters & Setters (Magic Methods)
    /**
     * It should skip attribute reading if property is not the referenceAttribute.
     */
    public function testAccessingUnknownPropertyThrowsException(): void
    {
        $item = new Item();

        $this->expectException(UnknownPropertyException::class);

        //@phpstan-ignore-next-line
        $item->nonExistentAttribute;
    }

    /**
     * It should gracefully handle access to unknown dynamic properties.
     *
     * This test ensures that accessing a non-existent dynamic property
     * on the behavior throws a `yii\base\UnknownPropertyException`,
     * verifying the fallback logic in `__get()`.
     *
     * @return void
     */
    public function testUnknownPropertyAccessTriggersExceptionSafely(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]);

        $item->attachBehavior('tags', $behavior);

        try {
            //@phpstan-ignore-next-line
            $item->getBehavior('tags')->nonExistent;
        } catch (UnknownPropertyException $e) {
            $this->assertStringContainsString('Getting unknown property', $e->getMessage());

            throw $e;
        }
    }

    /**
     * It should throw an exception when setting an unknown dynamic property.
     *
     * This test verifies that writing to a non-existent dynamic property
     * on the behavior triggers a `yii\base\UnknownPropertyException`,
     * confirming fallback behavior via `__set()`.
     *
     * @return void
     */
    public function testUnknownPropertySetTriggersExceptionSafely(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]);

        $item->attachBehavior('tags', $behavior);

        //@phpstan-ignore-next-line
        $item->getBehavior('tags')->nonExistent = [1, 2, 3];
    }

    /**
     * It should allow setting and getting the virtual reference attribute (e.g., tagIds).
     */
    public function testItShouldSetAndGetReferenceAttribute(): void
    {
        $item = new Item(['name' => 'Keyboard']);

        $item->tagIds = [1, 2];

        $this->assertEquals([1, 2], $item->tagIds);
        $this->assertTrue($item->canGetProperty('tagIds'));
        $this->assertTrue($item->canSetProperty('tagIds'));
    }

    /**
     * It should throw if a scalar is assigned to a reference attribute expecting an array or ActiveRecord.
     *
     * The behavior only accepts:
     * - scalar array values (for simple PKs)
     * - associative arrays (for composed PKs)
     * - instances of ActiveRecord
     * - or an array of any of the above
     *
     * Assigning a single scalar directly should raise an exception.
     */
    public function testReferenceAttributeThrowsIfScalarProvided(): void
    {
        $err = 'Invalid reference value for "tagIds". Expected an array or instance of yii\db\ActiveRecordInterface.';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($err);

        $item = new Item(['name' => 'Invalid Tag Input']);

        //@phpstan-ignore-next-line
        $item->tagIds = 1;
    }

    // SECTION: Relation Population
    /**
     * It should initialize the reference value correctly when using populateRelation with ActiveRecord instances.
     *
     * This ensures that calling `populateRelation()` with ActiveRecord objects
     * initializes the internal reference value accordingly.
     */
    public function testReferenceValueFromPopulateRelation(): void
    {
        $item = new Item(['name' => 'Teste Init']);

        $item->populateRelation('tags', [Tag::findOne(1), Tag::findOne(2), Tag::findOne(3)]);

        /** @var LinkManyToManyBehavior $behavior */
        $behavior = $item->getBehavior('tags');

        $this->assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $this->assertEquals([1, 2, 3], $item->tagIds);
    }

    /**
     * It should accept scalar values when assigning reference attribute directly.
     *
     * This verifies that assigning simple scalar identifiers to the virtual attribute
     * normalizes them correctly and makes them accessible through the getter.
     */
    public function testReferenceValueFromScalarIds(): void
    {
        $item = new Item(['name' => 'Teste Scalars']);

        $item->tagIds = [1, 2, 3];

        $this->assertEquals([1, 2, 3], $item->tagIds);
    }

    /**
     * It should accept ActiveRecord instances when assigning reference attribute.
     *
     * This validates that assigning a list of ActiveRecord models to the virtual attribute
     * extracts their primary keys properly and stores them as reference value.
     */
    public function testReferenceValueFromActiveRecordInstances(): void
    {
        $item = new Item(['name' => 'Teste AR']);

        $item->tagIds = [
            Tag::findOne(1),
            Tag::findOne(2),
            Tag::findOne(3),
        ];

        $this->assertEquals([1, 2, 3], $item->tagIds);
    }

    /**
     * It should normalize mixed scalar values and ActiveRecord instances when assigned.
     *
     * This ensures the behavior supports mixed input types for virtual attributes,
     * normalizing them consistently into a scalar primary key list.
     */
    public function testReferenceValueMixedScalarAndActiveRecord(): void
    {
        $item = new Item(['name' => 'Teste Mixed']);

        $item->tagIds = [
            1,
            Tag::findOne(2),
            3,
        ];

        $this->assertEquals([1, 2, 3], $item->tagIds);
    }

    /**
     * It should normalize a single ActiveRecord instance assigned directly to the virtual attribute.
     * Compatibility with Yii populateRelation method. This should not be used.
     */
    public function testReferenceValueFromSingleActiveRecord(): void
    {
        $item = new Item(['name' => 'Single AR']);

        //@phpstan-ignore-next-line
        $item->tagIds = Tag::findOne(2);

        $item->save();

        $item->refresh();

        $this->assertEquals([2], $item->tagIds);
    }

    // SECTION: Getters and Setters
    /**
     * It should throw if the relation is populated with invalid (non-ActiveRecord) values.
     *
     * This test simulates an invalid population of a relation with an object
     * that does not implement ActiveRecordInterface, which should trigger
     * an InvalidArgumentException during `getRelatedRecords()`.
     */
    public function testGetRelatedRecordsThrowsIfRelationContainsInvalidValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All records in relation');

        $item = new Item();
        $item->populateRelation('tags', [new stdClass()]); // invalid object

        /** @var LinkManyToManyBehavior $behavior */
        $behavior = $item->getBehavior('tags');

        $behavior->getRelatedRecords(); // should throw
    }

    /**
     * It should return empty array when relation is not populated.
     */
    public function testGetRelatedRecordsReturnsEmptyArrayWhenRelationIsNull(): void
    {
        $item = new Item(['name' => 'Empty Relation']);

        $item->detachBehavior('tags');
        $item->attachBehavior('tags', new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]));

        /** @var LinkManyToManyBehavior $behavior */
        $behavior = $item->getBehavior('tags');

        /** @var array $related */
        //@phpstan-ignore-next-line
        $related = $behavior->getRelatedRecords();

        $this->assertSame([], $related);
    }

    /**
     * It should throw if the internal reference value is missing expected primary key fields.
     *
     * This test forces an invalid reference value internally to simulate a corrupted state
     * and ensures the behavior throws an exception when attempting to access the getter.
     */
    public function testGetReferenceValueThrowsIfPrimaryKeyFieldIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected array with key');

        $item     = new Item();
        $behavior = $item->getBehavior('tags');

        self::assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $reflection = new ReflectionClass($behavior);

        $valueProp = $reflection->getProperty('referenceValueInternal');

        $valueProp->setAccessible(true);
        $valueProp->setValue($behavior, [['wrong_key' => 123]]);

        $manualProp = $reflection->getProperty('referenceWasSetManually');

        $manualProp->setAccessible(true);
        $manualProp->setValue($behavior, true);

        //@phpstan-ignore-next-line
        $item->tagIds;
    }

    /**
     * It should update the reference value automatically when the relation is dirty and not set manually.
     *
     * This test ensures that `getReferenceValue()` triggers `updateReferenceFromRelation()` when
     * the manual override flag is false and the relation hash indicates a change.
     */
    public function testReferenceValueTriggersAutoUpdateWhenDirty(): void
    {
        $item = new Item(['name' => 'Auto Update']);
        $item->populateRelation('tags', [Tag::findOne(1)]);

        $behavior = $item->getBehavior('tags');

        self::assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $reflection = new ReflectionClass($behavior);

        $manual = $reflection->getProperty('referenceWasSetManually');

        $manual->setAccessible(true);
        $manual->setValue($behavior, false);

        $internal = $reflection->getProperty('referenceValueInternal');

        $internal->setAccessible(true);
        $internal->setValue($behavior, null);

        $tagIds = $item->tagIds;

        $this->assertEquals([1], $tagIds);
    }

    // SECTION: Save (Insert & Update)
    /**
     * It should link new models after saving a new parent.
     */
    public function testItShouldLinkModelsOnSave(): void
    {
        $item = new Item(['name' => 'Mouse']);

        $item->save(false);

        $item->tagIds = [1, 2];

        $item->save(false);

        $this->assertCount(2, $item->tags);

        $this->assertEqualsCanonicalizing(
            [1, 2],
            array_map(static fn ($tag) => $tag->id, $item->tags)
        );
    }

    /**
     * It should skip afterSave() logic when reference value is not initialized.
     */
    public function testAfterSaveSkipsWhenReferenceNotInitialized(): void
    {
        $item = new Item(['name' => 'Skip afterSave']);

        /** @var LinkManyToManyBehavior $behavior */
        $behavior = $item->getBehavior('tags');

        $ref = new ReflectionClass($behavior);

        $prop = $ref->getProperty('referenceValueInternal');

        $prop->setAccessible(true);
        $prop->setValue($behavior, null);

        $event = new Event(['sender' => $item]);

        $behavior->afterSave($event);

        $this->assertFalse($behavior->getIsReferenceValueInitialized());
    }

    /**
     * It should throw an exception when the related model defines a composite primary key.
     *
     * This test ensures that the behavior explicitly rejects usage with models that declare
     * multi-column primary keys. Although composite keys may be supported in the future,
     * they are currently not allowed and should trigger an InvalidArgumentException.
     *
     * The relation is simulated using an anonymous class that overrides `primaryKey()`
     * to return multiple fields, without requiring actual database persistence.
     */
    public function testItShouldThrowIfRelatedModelHasCompositePrimaryKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Composite primary keys are not yet supported');

        $item = new class extends Item {
            public function getCompositeCategories(): ActiveQuery
            {
                return $this->hasMany(new class extends ActiveRecord {
                    public static function tableName(): string
                    {
                        return 'dummy';
                    }

                    public static function primaryKey(): array
                    {
                        return ['id', 'uid'];
                    }
                }, ['id' => 'category_id', 'uid' => 'category_uid'])
                ->viaTable('item_dummy', ['item_id' => 'id']);
            }
        };

        $item->detachBehavior('composite');
        $item->attachBehavior('composite', new LinkManyToManyBehavior([
            'relation'           => 'compositeCategories',
            'referenceAttribute' => 'compositeCategoryIds',
        ]));
    }

    /**
     * It should reset the manual override flag after saving the model.
     *
     * This test ensures that after the relation is synced during `afterSave()`,
     * the behavior resets the `referenceWasSetManually` flag to false.
     */
    public function testAfterSaveResetsManualOverride(): void
    {
        $item         = new Item(['name' => 'Manual Reset']);
        $item->tagIds = [1];

        $item->save(false);

        $behavior = $item->getBehavior('tags');

        self::assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $reflection = new ReflectionClass($behavior);

        $prop = $reflection->getProperty('referenceWasSetManually');

        $prop->setAccessible(true);

        $this->assertFalse($prop->getValue($behavior), 'Manual override flag should be reset after save');
    }

    /**
     * It should report relation as dirty when the reference value has not been initialized.
     *
     * This test ensures that `isReferenceRelationDirty()` returns true
     * when the internal reference value is still null.
     */
    public function testIsReferenceRelationDirtyReturnsTrueIfUninitialized(): void
    {
        $item     = new Item();
        $behavior = $item->getBehavior('tags');

        self::assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $reflection = new ReflectionClass($behavior);

        $internal = $reflection->getProperty('referenceValueInternal');

        $internal->setAccessible(true);
        $internal->setValue($behavior, null);

        $this->assertTrue($behavior->isReferenceRelationDirty());
    }

    /**
     * It should unlink models when removed from the reference attribute.
     *
     * @return void
     */
    public function testItShouldUnlinkModelsOnUpdate(): void
    {
        /** @var Item $item */
        $item = Item::findOne(1);

        $item->tagIds = [1, 2];

        $item->save(false);
        $item->refresh();

        $this->assertNotEmpty($item->tags, 'Expected tags before unlink');
        $this->assertCount(2, $item->tags);

        $item->tagIds = [];

        $item->save(false);

        $item->refresh();

        $this->assertCount(0, $item->tags, 'Expected tags to be unlinked');
    }

    /**
     * It should not trigger link/unlink if referenceAttribute was not set.
     */
    public function testReferenceAttributeNotSetDoesNothing(): void
    {
        $item = new Item();

        $item->name = 'No Reference Attribute';

        $item->save(false);

        $this->assertEmpty($item->tags, 'No tags should be linked.');
    }

    // SECTION: Delete Save Actions
    /**
     * It should unlink all models from junction table when the owner is deleted.
     */
    public function testItShouldUnlinkAllOnDelete(): void
    {
        /** @var Item $item */
        $item = Item::findOne(1);

        $item->tagIds = [1, 2];

        $item->save(false);

        $item->refresh();

        $this->assertNotNull($item);
        $this->assertNotEmpty($item->tags);

        $itemId = $item->id;

        $item->delete();

        $this->assertFalse(
            (new Query())->from('item_tag')->where(['item_id' => $itemId])->exists(),
            'Expected no records in junction table after delete'
        );
    }

    /**
     * Test that when `deleteOnUnlink` is true, unlinked relations are
     * physically removed from the junction table.
     *
     * This test verifies that:
     * - An item can be created and saved with multiple tags
     * - When one of the tag IDs is removed, the corresponding relation is deleted
     * - The junction table no longer contains the unlinked tag
     *
     * @return void
     */
    public function testDeleteOnUnlinkRemovesUnlinkedRelations(): void
    {
        $item = new Item();

        $item->name   = 'Test Item';
        $item->tagIds = [1, 2, 3];

        $this->assertTrue($item->save(), 'Initial save failed');
        $this->assertCount(3, $item->tags, 'Initial tags count should be 3');

        $item->tagIds = [1, 3];

        $this->assertTrue($item->save(), 'Update with reduced tags failed');

        $item->refresh();

        $tagIds = array_map(fn ($tag) => (int)$tag->id, $item->tags);

        sort($tagIds);

        $this->assertEquals([1, 3], $tagIds, 'Tag with ID 2 should have been unlinked');

        $exists = (new Query())
            ->from('item_tag')
            ->where(['item_id' => $item->id, 'tag_id' => 2])
            ->exists();

        $this->assertFalse($exists, 'junction table should not contain tag_id 2 after unlinking');
    }

    /**
     * Test that when `deleteOnUnlink` is false, unlinked relations are not
     * removed from the junction table.
     *
     * This ensures that:
     * - The unlink logic respects the configuration
     * - Soft unlinks are possible (e.g., for audit/history or custom cleanup)
     *
     * @return void
     */
    public function testUnlinkedRelationsAreNotDeletedWhenDeleteOnUnlinkIsFalse(): void
    {
        // Ensure required tags exist
        foreach ([10, 20] as $id) {
            /** @var Tag $tag */
            $tag = Tag::findOne($id);

            if (!$tag) {
                $tag = new Tag(['id' => $id, 'name' => "Tag $id"]);

                $tag->save(false);
            }
        }

        // Create item with both tag relations
        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
            'deleteOnUnlink'     => false,
            'extraColumns'       => [
                'external_id' => static fn (Tag $model) => $model->id,
            ],
        ]);

        $item->detachBehavior('tasgs');
        $item->attachBehavior('tags', $behavior);

        $item->name   = 'Soft Link Item';
        $item->tagIds = [10, 20];

        $this->assertTrue($item->save(), 'Initial save failed');

        $item->refresh();

        $this->assertCount(2, $item->tags, 'Initial tag count should be 2');

        // Modify tagIds to only keep one
        $item->tagIds = [10];

        $this->assertTrue($item->save(), 'Update with tag removed failed');

        $item->refresh();

        $tagIds = array_map(fn ($tag) => (int)$tag->id, $item->tags);

        $this->assertEquals([10], $tagIds, 'Remaining tag should be ID 10');

        // Now verify that the junction still contains the removed relation
        $exists = (new Query())
            ->from('item_tag')
            ->where(['item_id' => null, 'tag_id' => null, 'external_id' => 20])
            ->exists();

        $message = 'junction entry for external_id 20 should still exist when deleteOnUnlink=false';

        $this->assertTrue($exists, $message);
    }

    // SECTION: Sync Between Behavior and AR
    /**
     * It should reflect whether the reference value was set manually via setter.
     *
     * This test ensures that setting the virtual attribute manually marks
     * the behavior as "manually overridden", and that calling the `resetReferenceManualOverride()`
     * method clears that state properly.
     */
    public function testReferenceManualOverrideStateCanBeReset(): void
    {
        $item = new Item(['name' => 'Manual Flag']);

        $item->tagIds = [1];

        /** @var LinkManyToManyBehavior $behavior */
        $behavior = $item->getBehavior('tags');

        $this->assertTrue($behavior->isReferenceManualOverride(), 'Manual override should be true after set');

        $behavior->resetReferenceManualOverride();

        $this->assertFalse($behavior->isReferenceManualOverride(), 'Manual override should be false after reset');
    }

    // SECTION: Multiple Behaviors
    /**
     * It should support multiple LinkManyToManyBehavior instances on the same model.
     */
    public function testMultipleBehaviorsWorkIndependently(): void
    {
        $item = new Item();

        $item->name        = 'Multi-Behavior Item';
        $item->tagIds      = [1, 2];
        $item->categoryIds = [1, 2];

        $item->save(false);

        $this->assertCount(2, $item->tags, 'Tag relation should have 2 entries.');
        $this->assertCount(2, $item->categories, 'Category relation should have 2 entries.');
    }

    // SECTION: Extra Columns
    /**
     * It should resolve extraColumns using callables that receive the related model.
     */
    public function testExtraColumnsReceivesRelatedModel(): void
    {
        $called = false;

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
            'extraColumns'       => [
                'external_id' => function (Tag $model) use (&$called) {
                    $called = true;

                    $this->assertInstanceOf(ActiveRecordInterface::class, $model);

                    return $model->id;
                },
            ],
        ]);

        $item = new Item();

        $item->detachBehavior('tags');
        $item->attachBehavior('tags', $behavior);

        $item->name   = 'Test Item';
        $item->tagIds = [1];

        $item->save(false);

        $this->assertTrue($called, 'Extra column callable should have been called with related model.');
    }

    // SECTION: Testing Helpers
    /**
     * It should throw if a required primary key field is missing when building reference keys.
     *
     * This test calls `buildReferenceKey()` directly with a PK array that lacks the expected field,
     * ensuring the behavior validates all required fields are present before composing the key.
     */
    public function testBuildReferenceKeyThrowsIfFieldIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing primary key field");

        $item     = new Item();
        $behavior = $item->getBehavior('tags');

        self::assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $reflection = new ReflectionClass($behavior);
        $method     = $reflection->getMethod('buildReferenceKey');

        $method->setAccessible(true);

        $method->invoke($behavior, ['other_id' => 5], ['id']);
    }

    // SECTION: Helpers
    /**
     * Creates the necessary database tables for the test suite.
     *
     * This method checks if each required table already exists before
     * attempting to create it. This allows tests to be run repeatedly
     * without conflicts from duplicate table definitions.
     */
    protected function createTestTables(): void
    {
        $db = Yii::$app->getDb();

        $tables = $db->schema->getTableNames();

        if (!in_array('item', $tables)) {
            $db->createCommand()->createTable('item', [
                'id'   => 'pk',
                'name' => 'string NOT NULL',
            ])->execute();
        }

        if (!in_array('tag', $tables)) {
            $db->createCommand()->createTable('tag', [
                'id'          => 'pk',
                'external_id' => 'integer NULL',
                'name'        => 'string NOT NULL',
            ])->execute();
        }

        if (!in_array('category', $tables)) {
            $db->createCommand()->createTable('category', [
                'id'   => 'integer NOT NULL',
                'name' => 'string NOT NULL',
                'PRIMARY KEY(id)',
            ])->execute();
        }

        if (!in_array('item_tag', $tables)) {
            $db->createCommand()->createTable('item_tag', [
                'item_id'     => 'integer NULL',
                'tag_id'      => 'integer NULL',
                'external_id' => 'integer NULL',
                'PRIMARY KEY(item_id, tag_id, external_id)',
            ])->execute();
        }

        if (!in_array('item_category', $tables)) {
            $db->createCommand()->createTable('item_category', [
                'item_id'     => 'integer NOT NULL',
                'category_id' => 'integer NOT NULL',
                'PRIMARY KEY(item_id, category_id)',
            ])->execute();
        }
    }
}
