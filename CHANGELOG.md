# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] – 2025-04-18

### ✅ Added

- **4 new PHPUnit tests** improving coverage to 100%:
  - Exception handling when accessing unknown property on the model
  - Exception handling when accessing unknown dynamic property via `__get()`
  - Exception handling when setting unknown dynamic property via `__set()`
  - Proxy test for `normalizePrimaryKey()` with full path coverage
- Verified fallback behavior through `UnknownPropertyException` for unsupported dynamic access.
- All new tests are fully documented and contribute to line/method coverage.

## [1.1.0] – 2025-04-17

### 🚀 Added

- Full **Portuguese translation** of the README with complete usage documentation.
- Inline PHPDoc annotations using `@property` and `@property-read` for:
  - Virtual attributes (e.g. `tagIds`)
  - Related models (e.g. `Tag[] $tags`)
- Advanced example with support for `extraColumns`:
  - Includes `created_at` and `source` fields in the junction table.

### 🧼 Changed

- Updated docblocks across the behavior and usage examples for clarity and better static analysis.
- Anonymous closures now declared `static` when `$this` is not used — improving runtime performance.
- Updated terminology in documentation: **"pivot table" → "junction table"**, aligning with Yii2 core terminology.

### 🐛 Fixed

- Improved consistency in usage examples (e.g., setting virtual attributes).
- Confirmed full compatibility and test coverage across PHP `7.4` through `8.3`.

## [1.0.0] – 2025-04-16

### 🎉 Added

- Initial stable release of `yii2-m2m-behavior`.
- Core behavior to manage many-to-many relations in Yii2 via ActiveRecord.
- Support for virtual reference attributes (e.g. `categoryIds`).
- Automatic syncing on insert, update and delete.
- Optional deletion of junction table records via `deleteOnUnlink` flag.
- Support for `extraColumns` with static values or callables.
- Fully tested behavior with PHPUnit and in-memory SQLite.
- Multiple attribute support (multiple behaviors per model).
- Magic getter and setter for reference attribute.
- PSR-4 compliant structure.
- Full documentation in README, including advanced examples.
- Declared support for PHP 7.4 to 8.3 (fully tested up to 8.2).
