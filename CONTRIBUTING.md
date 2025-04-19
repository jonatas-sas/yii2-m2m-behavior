# Contributing Guide

Thank you for your interest in contributing to **Yii2 Many to Many Behavior**!
Your help is essential to keeping this project functional, efficient, and aligned with the Yii2 ecosystem.

---

## 📋 Code of Conduct

Please follow our [Code of Conduct](./CODE_OF_CONDUCT.md) in all your interactions.

---

## 🛠 Requirements

Before contributing:

- Familiarize yourself with Yii2 and its behaviors system.
- Follow the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard.
- Ensure your code is tested and passes CI.
- Consider other relevant PHP Standards Recommendations (PSRs):
  - [PSR-1](https://www.php-fig.org/psr/psr-1/): Basic Coding Standard
  - [PSR-4](https://www.php-fig.org/psr/psr-4/): Autoloader Standard
  - [PSR-5 (Draft)](https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc.md): PHPDoc Standard

---

## 🚀 How to Contribute

### 1. Fork & Clone

```bash
git clone https://github.com/YOUR_USERNAME/yii2-m2m-behavior.git
cd yii2-m2m-behavior
```

### 2. Install dependencies

```bash
composer install
```

### 3. Run tests

```bash
vendor/bin/phpunit
```

To generate code coverage:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text
```

### 4. Lint your code

```bash
composer lint
```

> We use PHP_CodeSniffer with PSR-12 rules.

### 5. Submit a Pull Request

- Make sure your code is **PSR-12 compliant**.
- Include **PHPDoc comments** for public and protected methods.
- Document any new feature or behavior clearly.
- Add or update tests to ensure full code coverage.
- Reference the related issue or feature request.

---

## ✅ Good Practices

- Use meaningful commit messages.
- Group related changes into separate commits.
- Keep PRs focused and minimal.
- Add fixtures only if necessary, and keep them reusable.
- Favor named variables and expressive tests.

---

## 🧪 Testing Philosophy

We aim for high-quality, readable and deterministic tests:

- Use **in-memory SQLite** (already configured).
- Favor **unit tests**, but allow behavior/integration when needed.
- Avoid side effects in test methods.
- Test **edge cases** (nulls, duplicates, invalid IDs).

---

## 🙌 Thank You!

Your contributions make this package better and more useful to the Yii2 community. We're happy to have you onboard!
