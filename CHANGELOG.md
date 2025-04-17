# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] – 2025-04-17

### 🎉 Added

- Initial stable release of `yii2-m2m-behavior`.
- Core behavior to manage many-to-many relations in Yii2 via ActiveRecord.
- Support for virtual reference attributes (e.g. `categoryIds`).
- Automatic syncing on insert, update and delete.
- Optional deletion of pivot table records via `deleteOnUnlink` flag.
- Support for `extraColumns` with static values or callables.
- Fully tested behavior with PHPUnit and in-memory SQLite.
- Multiple attribute support (multiple behaviors per model).
- Magic getter and setter for reference attribute.
- PSR-4 compliant structure.
- Full documentation in README, including advanced examples.
- Declared support for PHP 7.4 to 8.3 (fully tested up to 8.2).

---

## [Unreleased]

### 🧪 CI and Tooling (Planned)

- GitHub Actions for CI with PHPUnit and PHPStan
- Code coverage integration (Coveralls or Codecov)
- Static analysis with PHPStan
- Compatibility matrix for PHP 7.4, 8.0, 8.1, 8.2, 8.3
