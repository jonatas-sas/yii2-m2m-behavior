# Yii2 Many to Many Behavior

<p align="center">
  <!-- L1: PHP, Yii, Packagist, License -->
  <a href="https://www.php.net/releases/8.1/en.php" title="PHP Version 8.1+" target="_blank" rel="noopener noreferrer">
    <img src="https://img.shields.io/badge/PHP-8.1+-8892BF.svg?style=flat-square&logo=php" alt="PHP 8.1+">
  </a>
  <a href="https://www.yiiframework.com/" title="Yii Framework Website" target="_blank" rel="noopener noreferrer">
    <img src="https://img.shields.io/badge/Powered-by-Yii_Framework-green.svg?style=flat-square" alt="Powered by Yii Framework">
  </a>
  <a href="https://packagist.org/packages/jonatas-sas/yii2-m2m-behavior" title="View on Packagist" target="_blank" rel="noopener noreferrer">
    <img src="https://img.shields.io/packagist/v/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square" alt="Packagist Version">
  </a>
  <a href="LICENSE" title="View License">
    <img src="https://img.shields.io/packagist/l/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square" alt="License">
  </a>
</p>

<p align="center">
  <!-- L2: Lint, Static, Tests -->
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/lint.yml" title="Lint Workflow">
    <img src="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/lint.yml/badge.svg" alt="Lint Status">
  </a>
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/static.yml" title="Static Analysis Status">
    <img src="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/static.yml/badge.svg" alt="Static Analysis">
  </a>
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/test.yml" title="Test Workflow">
    <img src="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/test.yml/badge.svg" alt="Tests Status">
  </a>
</p>

<p align="center">
  <!-- L3: Security, Dependabot, Codecov -->
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/security.yml" title="Security Scan">
    <img src="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/security.yml/badge.svg" alt="Security Status">
  </a>
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/dependabot/dependabot-updates" title="Dependabot Updates">
    <img src="https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/dependabot/dependabot-updates/badge.svg" alt="Dependabot">
  </a>
  <a href="https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior" title="Code Coverage" target="_blank" rel="noopener noreferrer">
    <img src="https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior/branch/main/graph/badge.svg" alt="Coverage">
  </a>
</p>

<p align="center">
  <!-- L4: Downloads, Issues, PRs -->
  <a href="https://packagist.org/packages/jonatas-sas/yii2-m2m-behavior/stats" title="Total Downloads" target="_blank" rel="noopener noreferrer">
    <img src="https://img.shields.io/packagist/dt/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square" alt="Total Downloads">
  </a>
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/issues" title="Open Issues">
    <img src="https://img.shields.io/github/issues/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square" alt="Open Issues">
  </a>
  <a href="https://github.com/jonatas-sas/yii2-m2m-behavior/pulls" title="Open Pull Requests">
    <img src="https://img.shields.io/github/issues-pr/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square" alt="Open Pull Requests">
  </a>
</p>

<p align="center">
  <img src="https://www.yiiframework.com/image/logo/yii.png" alt="Yii Framework Logo" width="160" title="Yii Framework Logo">
</p>

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
H
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
