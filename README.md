# Yii2 Many to Many Behavior

[![Version](https://img.shields.io/packagist/v/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square)](https://packagist.org/packages/jonatas-sas/yii2-m2m-behavior)
[![License](https://img.shields.io/packagist/l/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square)](LICENSE)
[![Lint](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/lint.yml/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/lint.yml)
[![Static](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/static.yml/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/static.yml)
[![Tests](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/test.yml/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/test.yml)
[![Security](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/security.yml/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/security.yml)
[![Dependabot](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/dependabot/dependabot-updates/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/dependabot/dependabot-updates)
[![Coverage](https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior/branch/main/graph/badge.svg)](https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior)
[![Yii2](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat-square)](https://www.yiiframework.com/)

A reusable and robust behavior for managing many-to-many (M2M) relationships in **Yii2 ActiveRecord** using virtual attributes.

> 🧩 Inspired by the archived [`yii2tech/ar-linkmany`](https://github.com/yii2tech/ar-linkmany) package by [Paul Klimov](https://github.com/PaulKlimov), now extended with modern improvements, full test coverage, and long-term support.

---

## 📦 Installation

```bash
composer require jonatas-sas/yii2-m2m-behavior
```

---

## 📚 Documentation

- 📘 [English Docs](docs/index.md)
- 🇧🇷 [Documentação em Português](docs/index.pt_BR.md)

---

## 🚀 Overview

Yii2 Many to Many Behavior helps you:

- Manage M2M relations using **virtual attributes** (e.g. `tagIds`).
- Automatically sync relations on `insert`, `update`, and `delete`.
- Control deletion of junction table rows (`deleteOnUnlink`).
- Add **extra columns** to junction records (e.g. timestamps or metadata).
- Integrate smoothly into **ActiveForm**, **GridView**, and **DetailView**.

---

## 🛠 Example Usage (PHP 8.1+)

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

### Example Form Field

```php
echo $form->field($model, 'tagIds')->checkboxList(
    Tag::find()
        ->select(['name', 'id'])
        ->indexBy('id')
        ->column()
);
```

---

## 🤝 Contributing

Found a bug or want to suggest an improvement?

- Read the [Contributing Guide](CONTRIBUTING.md)
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) and [Yii2 coding practices](https://www.yiiframework.com/doc/guide/2.0/en)

---

## 🛡 License

Yii2 Many to Many Behavior is released under the MIT License.

---

## 💙 Credits

Maintained by the Yii2 community.\
Inspired by the Yii2Tech package and rebuilt with care for modern development.

---

A reusable and robust behavior for managing many-to-many (M2M) relationships in **Yii2 ActiveRecord** using virtual attributes.

> 🧩 Inspired by the archived [`yii2tech/ar-linkmany`](https://github.com/yii2tech/ar-linkmany) package by [Paul Klimov](https://github.com/PaulKlimov), now extended with modern improvements, full test coverage, and long-term support.

---

## 📦 Installation

```bash
composer require jonatas-sas/yii2-m2m-behavior
```

---

## 📚 Documentation

- 📘 [English Docs](docs/index.md)
- 🇧🇷 [Documentação em Português](docs/index.pt_BR.md)

---

## 🚀 Overview

Yii2 Many to Many Behavior helps you:

- Manage M2M relations using **virtual attributes** (e.g. `tagIds`).
- Automatically sync relations on `insert`, `update`, and `delete`.
- Control deletion of junction table rows (`deleteOnUnlink`).
- Add **extra columns** to junction records (e.g. timestamps or metadata).
- Integrate smoothly into **ActiveForm**, **GridView**, and **DetailView**.

---

## 🛠 Example Usage

```php
use yii\db\ActiveRecord;
use odara\yii\behaviors\LinkManyToManyBehavior;

/**
 * @property int   $id
 * @property string $name
 * @property string|null $source
 * @property int|null $created_at
 * @property Tag[] $tags
 * @property int[] $tagIds
 */
class Item extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'tags' => [
                'class'              => LinkManyToManyBehavior::class,
                'relation'           => 'tags',
                'referenceAttribute' => 'tagIds',
                'deleteOnUnlink'     => true,
                'extraColumns'       => [
                    'source'     => 'admin',
                    'created_at' => static fn() => time(),
                ],
            ],
        ];
    }

    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('item_tag', ['item_id' => 'id']);
    }
}
```

### Example Form Field

```php
echo $form->field($model, 'tagIds')->checkboxList(Tag::find()->select(['name', 'id'])->indexBy('id')->column());
```

---

## 🤝 Contributing

Found a bug or want to suggest an improvement?

- Read the [Contributing Guide](CONTRIBUTING.md)
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) and [Yii2 coding practices](https://www.yiiframework.com/doc/guide/2.0/en)

---

## 🛡 License

Yii2 Many to Many Behavior is released under the MIT License.

---

## 💙 Credits

Maintained by the Yii2 community.
Inspired by the Yii2Tech package and rebuilt with care for modern development.

---
