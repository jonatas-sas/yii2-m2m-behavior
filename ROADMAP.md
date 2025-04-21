# 🚣️ Roadmap – Yii2 Many to Many Behavior

This roadmap outlines planned features and enhancements for the package.

---

## ✅ Completed

- ✅ 100% test coverage
- ✅ Full PHPDoc documentation
- ✅ Portuguese and English documentation
- ✅ GitHub Actions for CI, lint, and coverage
- ✅ Contributing and Code of Conduct
- ✅ PSR-12 compliant (PHP_CodeSniffer)
- ✅ Despersonalized branding
- ✅ New docs structure under `docs/`
- ✅ Junction table support with `extraColumns`

---

## 🚧 In Progress

- [ ] Finalize docs examples for GridView and DetailView

---

## 🔮 Planned Features

### ⚙️ Core Behavior Enhancements

- [ ] Support for **dynamic getter generation**
  - Automatically expose `$tagIds`, `$categoryIds`, etc.
  - Optional PSR-5/IDE helper generation for stubs
- [ ] Built-in support for **validators** based on `referenceAttribute`
  - Example: `each` rule for ID validation
- [ ] **Relation cache optimization**
  - Reduce unnecessary re-fetching from DB
  - Optional cache per lifecycle (e.g. per request or update)

### 🧩 Yii Integration

- [ ] **Debug Panel Integration**

  - Track operations like `link`, `unlink`, `unlinkAll`
  - Display model, relation, linked/unlinked IDs, extra columns
  - Interactive UI using Yii2 Debug Module (`yii\debug\Panel`)

- [ ] **Gii Generator Extension**
  - Optionally scaffold behaviors directly from relation definition

### ⚠️ Events System

- [ ] Emit custom events:
  - `beforeLink`, `afterLink`
  - `beforeUnlink`, `afterUnlink`
  - Allow cancellation or mutation of data

### 📦 Composer & Ecosystem

- [ ] Generate typed stubs for IDEs (PHPStorm, etc.)
- [ ] Add support for Yii3 split package (future-proof)

---

## 🧪 Tests & Quality

- [ ] Add form submission tests (invalid/missing relations)
- [ ] Add mutation tests for `extraColumns` with complex logic
- [ ] Add static analysis integration with Psalm (in addition to PHPStan)

---

## 🤝 Community

- [ ] Add GitHub Discussions
- [x] Add Templates for Bug / Feature Reports
