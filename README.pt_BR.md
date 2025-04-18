# Behavior Yii2 para Relações Many-to-Many 🌟

[![Versão](https://img.shields.io/packagist/v/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square)](https://packagist.org/packages/jonatas-sas/yii2-m2m-behavior)
[![Licença](https://img.shields.io/packagist/l/jonatas-sas/yii2-m2m-behavior.svg?style=flat-square)](LICENSE)
[![Testes](https://github.com/jonatas-sas/yii2-m2m-behavior/actions/workflows/tests.yml/badge.svg)](https://github.com/jonatas-sas/yii2-m2m-behavior/actions)
[![Cobertura](https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior/branch/main/graph/badge.svg)](https://codecov.io/gh/jonatas-sas/yii2-m2m-behavior)

## Sobre

> 🇧🇷 Este é o README em português. Para a versão em inglês, [clique aqui](README.md).

Este pacote fornece um comportamento Yii2 reutilizável para salvar relações **many-to-many** (muitos para muitos) usando `ActiveRecord` e atributos virtuais.

> ✨ Baseado no pacote arquivado [`yii2tech/ar-linkmany`](https://github.com/yii2tech/ar-linkmany) de [Paul Klimov](https://github.com/PaulKlimov), com melhorias, suporte contínuo, testes atualizados e cobertura completa.

---

## 🚀 Instalação

```bash
composer require jonatas-sas/yii2-m2m-behavior
```

---

## 🧠 Como Funciona?

Este comportamento permite sincronizar uma relação many-to-many com um atributo virtual no seu modelo. Ele:

- Permite atribuir os **IDs** relacionados diretamente.
- Suporta colunas extras na junction table (tabela de junção).
- Pode deletar ou manter os relacionamentos não referenciados.
- Integra-se com eventos de `afterInsert`, `afterUpdate` e `afterDelete`.

---

## 🛠️ Exemplo de Uso

```php
use odara\yii\behaviors\LinkManyToManyBehavior;

/**
 * Classe de exemplo com behavior de relação many-to-many.
 *
 * @property int        $id
 * @property string     $name
 * @property string     $source
 * @property int        $created_at
 *
 * @property int[]      $tagIds IDs das tags relacionadas (virtual)

 * @property-read Tag[] $tags   Instâncias de Tag relacionadas (read-only)
 */
class Post extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'm2m' => [
                'class'              => LinkManyToManyBehavior::class,
                'relation'           => 'tags',
                'referenceAttribute' => 'tagIds',
                'deleteOnUnlink'     => true,
                'extraColumns'       => [
                    'created_at' => static fn() => time(),
                    'source'     => 'admin',
                ],
            ],
        ];
    }

    /**
     * Retorna a relação many-to-many com Tag.
     *
     * @return \yii\db\ActiveQuery
     * @property-read Tag[] $tags
     */
    public function getTags()
    {
        return $this->hasMany(Tag::class, ['id' => 'tag_id'])
            ->viaTable('post_tag', ['post_id' => 'id']);
    }
}
```

### Exemplo de uso do atributo virtual

```php
$post = Post::findOne(1);
$post->tagIds = [1, 2, 3];
$post->save();
```

---

## ⚙️ Opções Disponíveis

| Opção                | Tipo     | Descrição                                                            |
| -------------------- | -------- | -------------------------------------------------------------------- |
| `relation`           | `string` | Nome da relação (ex: `tags`)                                         |
| `referenceAttribute` | `string` | Nome do atributo virtual (ex: `tagIds`)                              |
| `deleteOnUnlink`     | `bool`   | Remove os registros da junction table se não estiverem mais na lista |
| `extraColumns`       | `array`  | Colunas adicionais para salvar na junction table                     |

---

## 📆 Recursos Avançados

- Suporte a múltiplos behaviors por modelo
- Colunas extras com valores estáticos ou dinâmicos (closures)
- Atributo virtual é totalmente integrado (getter, setter, reflexão, etc.)
- Suporte a acesso via `__get()` e `__set()`
- Compatível com ferramentas de análise estática (`@property`, `@property-read`)

---

## ✅ Testes

O pacote é testado em PHP `7.4` até `8.3` e usa PHPUnit com cobertura total.

Execute os testes localmente com:

```bash
composer install
vendor/bin/phpunit
```

---

## 🤝 Contribuição

Contribuições são bem-vindas! Abra uma issue ou envie um PR com testes e descrição clara.

---

## 📜 Licença

Código aberto sob a [Licença MIT](LICENSE).

---

## 🌟 Sobre

Desenvolvido com ❤️ no Brasil por [Jonatas Sas](https://github.com/jonatas-sas).
