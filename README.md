# Yii2 Many to Many Behavior

[![License](https://img.shields.io/github/license/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square)](LICENSE)
[![Tests](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/tests.yml/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior/branch/main/graph/badge.svg)](https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior)

## Overview

**yii2-m2m-behavior** is a Yii2 behavior to simplify managing many-to-many relations via ActiveRecord. It synchronizes related records based on a virtual attribute (e.g., `categoryIds`) without manual `link()` and `unlink()` calls.

## Requirements

- PHP 7.4 or higher (tested up to 8.3)
- Yii 2.0 or later

## Installation

Install via Composer:

```bash
composer require jonatas-sas/yii2-m2m-behavior
```

## Basic Usage

```php
use odara\yii\behaviors\LinkManyToManyBehavior;

class Item extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'm2m' => [
                'class' => LinkManyToManyBehavior::class,
                'relation' => 'categories',
                'referenceAttribute' => 'categoryIds',
            ],
        ];
    }

    public function getCategories()
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])
            ->viaTable('item_category', ['item_id' => 'id']);
    }
}
```

> ✅ The `categoryIds` attribute is virtual and automatically handled.

## Advanced Configuration

### Delete on Unlink

```php
'deleteOnUnlink' => false // Prevents deleting pivot rows when unlinked
```

### Extra Columns

```php
'extraColumns' => [
    'created_at' => fn() => time(),
    'source' => 'form',
]
```

### Multiple Behaviors

```php
return [
    'categoriesRelation' => [...],
    'tagsRelation' => [...],
];
```

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

## Quality Tools

- PHPUnit for tests
- PHPStan for static analysis
- Coveralls for test coverage

## Contributing

Contributions are welcome:

1. Fork this repository
2. Create a feature branch: `feature/my-feature`
3. Add tests and make your changes
4. Submit a pull request

Make sure to follow PSR-12 and existing project structure.

## Credits

This package is based on the work of **Paul Klimov** in [yii2tech/ar-linkmany](https://github.com/yii2tech/ar-linkmany), which is now archived and unmaintained. This implementation modernizes and extends the original behavior with better documentation, broader compatibility, and complete test coverage.
