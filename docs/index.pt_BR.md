# Yii2 Many to Many Behavior

Um comportamento simples e flexível para o Yii2 que permite gerenciar relações muitos-para-muitos com ActiveRecord e atributos virtuais.

---

## 📖 Sumário

- [Introdução](#introducao)
- [Instalação](#instalacao)
- [Como Funciona](#como-funciona)
- [Exemplo de Uso](#exemplo-de-uso)
- [Integração com Widgets do Yii2](#integracao-com-widgets-do-yii2)
  - [ActiveForm](#activeform)
  - [GridView](#gridview)
  - [DetailView](#detailview)
- [Opções](#opcoes)
- [Funcionalidades Avançadas](#funcionalidades-avancadas)
- [Anotações PHPDoc](#anotacoes-phpdoc)

---

## 🧩 Introdução

O `Yii2 Many to Many Behavior` permite que modelos ActiveRecord sincronizem automaticamente relações muitos-para-muitos usando um atributo virtual. Ele lida com vinculação, desvinculação e colunas extras na tabela de junção.

Inspirado originalmente em `yii2tech/ar-linkmany`, esse pacote oferece:

- Cobertura total de testes
- Suporte ativo e contínuo
- Estrutura moderna de código (PSR-4, análise estática)

---

## ⚙️ Instalação

```bash
composer require jonatas-sas/yii2-m2m-behavior
```

---

## 🔍 Como Funciona

O comportamento sincroniza uma relação `hasMany` definida via tabela de junção. Você define:

- `relation`: nome do método da relação
- `referenceAttribute`: atributo virtual usado para IDs

O comportamento escuta os seguintes eventos do modelo:

- `afterInsert`
- `afterUpdate`
- `afterDelete`

E então vincula ou desvincula os registros automaticamente.

---

## 💡 Exemplo de Uso

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

Uso:

```php
$item = Item::findOne(1);

$item->tagIds = [1, 2, 3];

$item->save();
```

---

## 🧍 Integração com Widgets do Yii2

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

## 🔧 Opções

| Opção                | Tipo   | Descrição                                                             |
| -------------------- | ------ | --------------------------------------------------------------------- |
| `relation`           | string | Nome do método da relação (ex: `tags`)                                |
| `referenceAttribute` | string | Nome do atributo virtual (ex: `tagIds`)                               |
| `deleteOnUnlink`     | bool   | Remove os registros da tabela de junção ao desvincular (padrão: true) |
| `extraColumns`       | array  | Colunas extras a serem salvas na tabela de junção                     |

---

## 🚀 Funcionalidades Avançadas

- Suporte a vários behaviors por modelo (ex: `tagIds`, `categoryIds`)
- Suporte a colunas extras com valores fixos ou `closures`
- Getter/setter automáticos para o atributo virtual
- Fallback completo para `__get` e `__set`
- Compatível com chaves primárias compostas

---

## 📜 Anotações PHPDoc

Use as anotações abaixo nos seus modelos para melhor suporte em IDEs:

```php
/**
 * @property      int[] $tagIds
 * @property-read Tag[] $tags
 */
```
