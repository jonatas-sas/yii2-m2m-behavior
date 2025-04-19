# Yii2 Many to Many Behavior

A lightweight and flexible Yii2 behavior for managing many-to-many relations using ActiveRecord and virtual attributes.

---

## 📖 Table of Contents

- [Introduction](#introduction)
- [Installation](#installation)
- [How It Works](#how-it-works)
- [Usage Example](#usage-example)
- [Yii2 Widgets Integration](#yii2-widgets-integration)
  - [ActiveForm](#activeform)
  - [GridView](#gridview)
  - [DetailView](#detailview)
- [Options](#options)
- [Advanced Features](#advanced-features)
- [PHPDoc Annotations](#phpdoc-annotations)

---

## 🧩 Introduction

`Yii2 Many to Many Behavior` allows ActiveRecord models to automatically sync many-to-many relations through a virtual attribute, handling linking, unlinking, and optional extra columns on the junction table.

Originally inspired by `yii2tech/ar-linkmany`, this package brings:

- Full test coverage
- Continuous support
- Modern code structure (PSR-4, static analysis ready)

---

## ⚙️ Installation

```bash
composer require jonatas-sas/yii2-m2m-behavior
```

---

## 🔍 How It Works

The behavior synchronizes a `hasMany` relation defined via a junction table. You define:

- `relation`: the relation method name
- `referenceAttribute`: a virtual attribute to assign related model IDs

The behavior listens to the following model events:

- `afterInsert`
- `afterUpdate`
- `afterDelete`

It then links/unlinks records accordingly.

---

## 💡 Usage Example

```php
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use odara\yii\behaviors\LinkManyToManyBehavior;

/**
 * @property int        $id
 * @property string     $name
 *
 * @property-read Tag[] $tags
 * @property int[]      $tagIds
 */
class Item extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'tags' => [
                'class' => LinkManyToManyBehavior::class,
                'relation' => 'tags',
                'referenceAttribute' => 'tagIds',
                'deleteOnUnlink' => true,
                'extraColumns' => [
                    'source' => 'admin',
                    'created_at' => static fn (): int => time(),
                ],
            ],
        ];
    }

    /**
     * Returns the relation between Item and Tag models.
     *
     * @return ActiveQuery
     */
    public function getTags(): ActiveQuery
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('item_tag', ['item_id' => 'id']);
    }
}
```

Usage:

```php
$item = Item::findOne(1);

$item->tagIds = [1, 2, 3];

$item->save();
```

---

## 🧩 Yii2 Widgets Integration

### ActiveForm

```php
echo $form->field($model, 'tagIds')->checkboxList(
    ArrayHelper::map(Tag::find()->all(), 'id', 'name')
);
```

### GridView

```php
[
    'attribute' => 'tags',
    'value'     => fn($model) => implode(', ', ArrayHelper::getColumn($model->tags, 'name')),
]
```

### DetailView

```php
[
    'attribute' => 'tags',
    'value'     => implode(', ', ArrayHelper::getColumn($model->tags, 'name')),
]
```

---

## 🔧 Options

| Option               | Type   | Description                                                            |
| -------------------- | ------ | ---------------------------------------------------------------------- |
| `relation`           | string | Name of the relation method (e.g. `tags`)                              |
| `referenceAttribute` | string | Virtual attribute name (e.g. `tagIds`)                                 |
| `deleteOnUnlink`     | bool   | Whether to delete unlinked records from junction table (default: true) |
| `extraColumns`       | array  | Extra columns to insert into the junction table                        |

---

## 🚀 Advanced Features

- Multiple behaviors per model (e.g. `tagIds`, `categoryIds`)
- Callable or static values for `extraColumns`
- Automatic magic getter/setter for `referenceAttribute`
- Full fallback to `__get` and `__set` when attribute does not match
- Works with composite primary keys

---

## 📚 PHPDoc Annotations

Use the following annotations in your models to improve IDE support:

```php
/**
 * @property      int[] $tagIds
 * @property-read Tag[] $tags
 */
```
