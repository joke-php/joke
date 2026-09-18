<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response\Html;

/**
 * Сервис для импорта структуры из сырого HTML-документа в PageBuilder.
 *
 * Использует встроенный DOMDocument для надежного разбора HTML. Позволяет интегрировать
 * готовые HTML-страницы или фрагменты, сохраняя
 * возможность управления ресурсами (скрипты, стили) и атрибутами через билдер.
 */
class HtmlImporter
{
    /**
     * Проверяет, является ли переданная строка полноценным HTML-документом.
     *
     * Эвристика основана на наличии объявления DOCTYPE или открывающих тегов
     * структурных элементов: <html>, <head> или <body>.
     *
     * @param string $html входная строка с HTML-разметкой
     *
     * @return bool true, если обнаружены признаки полноценного документа
     */
    public static function isFullHtmlDocument(string $html): bool
    {
        return (bool) preg_match('/(?:<!doctype|<(?:html|head|body))\b/i', $html);
    }

    /**
     * Импортирует структуру HTML-документа в экземпляр PageBuilder.
     *
     * Метод разбирает HTML, извлекая:
     * - Заголовок страницы (<title>)
     * - Кодировку (из <meta charset> или устаревшего http-equiv)
     * - Мета-теги с атрибутом name
     * - Атрибуты тегов <html> и <body>
     * - Содержимое <body>
     * - Остальное содержимое <head> (стили, скрипты, прочие meta) добавляется как сырая строка.
     *
     * Если теги <head> и <body> не найдены, весь HTML считается контентом тела.
     *
     * @param PageBuilder $builder целевой билдер, который будет заполнен данными
     * @param string      $html    исходный HTML-код (фрагмент или полный документ)
     *
     * @todo Обработка DOCTYPE (сейчас игнорируется при сборке билдером).
     */
    public static function import(PageBuilder $builder, string $html): void
    {
        $doc = new \DOMDocument();
        // Подавляем предупреждения парсера, так как пользовательский HTML может быть невалидным
        @$doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $headTag = $doc->getElementsByTagName('head')->item(0);
        $bodyTag = $doc->getElementsByTagName('body')->item(0);

        if (!$headTag && !$bodyTag) {
            $builder->setContent($html);

            return;
        }
        if ($headTag) {
            self::prepareHead($doc, $headTag, $builder);
        }
        $htmlTag = $doc->getElementsByTagName('html')->item(0);
        if ($htmlTag) {
            self::prepareAttributes($htmlTag->attributes, $builder->htmlAttributes);
        }
        if ($bodyTag) {
            self::prepareBody($doc, $bodyTag, $builder);
        }
    }

    /**
     * Разбирает содержимое тега <body> и переносит его в билдер.
     *
     * Извлекает атрибуты тега <body> и сохраняет всё внутреннее содержимое
     * (innerHTML) как основной контент страницы.
     *
     * @param \DOMDocument              $doc     экземпляр DOM-документа для сериализации узлов
     * @param null|\DOMElement|\DOMNode $bodyTag узел тега <body>
     * @param PageBuilder               $builder целевой билдер
     */
    private static function prepareBody(
        \DOMDocument $doc,
        \DOMNode|\DOMElement|null $bodyTag,
        PageBuilder $builder,
    ): void {
        self::prepareAttributes($bodyTag->attributes, $builder->bodyAttributes);
        $body = '';
        foreach ($bodyTag->childNodes as $child) {
            $body .= $doc->saveHTML($child);
        }
        $builder->setContent($body);
    }

    /**
     * Разбирает содержимое тега <head> и распределяет его по билдеру.
     *
     * Логика обработки:
     * - <title> -> PageBuilder::setTitle()
     * - <meta charset="..."> -> PageBuilder::setCharset()
     * - <meta http-equiv="Content-Type"> -> Парсинг charset из content
     * - <meta name="..."> -> PageBuilder::addMeta()
     * - Остальные узлы (link, style, script, прочие meta) -> PageBuilder::headString
     *
     * Пустые текстовые узлы (пробелы, переносы строк между тегами) игнорируются.
     *
     * @param \DOMDocument $doc     экземпляр DOM-документа для сериализации узлов
     * @param \DOMNode     $headTag узел тега <head>
     * @param PageBuilder  $builder целевой билдер
     */
    private static function prepareHead(\DOMDocument $doc, \DOMNode|\DOMElement $headTag, PageBuilder $builder): void
    {
        foreach ($headTag->childNodes as $child) {
            if (
                (XML_TEXT_NODE === $child->nodeType && '' === trim($child->textContent))
                || !$child instanceof \DOMElement
            ) {
                continue;
            }
            if ('meta' === $child->nodeName) {
                $charsetAttr = $child->getAttribute('charset');
                if (!empty($charsetAttr)) {
                    $builder->setCharset($charsetAttr);
                } elseif ('Content-Type' === $child->getAttribute('http-equiv')) {
                    $content = $child->getAttribute('content');
                    if (preg_match('/charset\s*=\s*["\']?([^"\';(\s)]+)/i', $content, $matches)) {
                        $builder->setCharset($matches[1]);
                    }
                } elseif ('' !== $child->getAttribute('name')) {
                    $builder->addMeta($child->getAttribute('name'), $child->getAttribute('content'));
                } else {
                    $builder->headString->add($doc->saveHTML($child));
                }
            } elseif ('title' === $child->nodeName) {
                $builder->setTitle($child->textContent);
            } else {
                $builder->headString->add($doc->saveHTML($child));
            }
        }
    }

    /**
     * Переносит атрибуты из DOM-узла в AttributeCollection.
     *
     * @param \DOMNamedNodeMap    $attributes карта атрибутов DOM-узла
     * @param AttributeCollection $collection коллекция для заполнения
     */
    private static function prepareAttributes(\DOMNamedNodeMap $attributes, AttributeCollection $collection): void
    {
        foreach ($attributes as $attr) {
            if ($attr instanceof \DOMAttr) {
                $collection->set($attr->name, $attr->value);
            }
        }
    }
}
