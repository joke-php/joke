<?php

declare(strict_types=1);

namespace Vasoft\Joke\Http\Response;

use Vasoft\Joke\Config\Environment;
use Vasoft\Joke\Container\ServiceContainer;
use Vasoft\Joke\Http\Cookies\CookieConfig;
use Vasoft\Joke\Http\Response\Html\Asset\AssetFileManager;
use Vasoft\Joke\Http\Response\Html\HtmlImporter;
use Vasoft\Joke\Http\Response\Html\PageBuilder;
use Vasoft\Joke\Http\Response\Html\PageBuilderConfig;
use Vasoft\Joke\Support\Normalizers\Path;

/**
 * Расширенный HTML-ответ с поддержкой программной сборки страницы.
 *
 * В отличие от базового HtmlResponse, этот класс использует PageBuilder для
 * формирования структуры документа (<html>, <head>, <body>). Это позволяет:
 * - Динамически подключать CSS/JS ресурсы через мидлвары и контроллеры.
 * - Управлять мета-тегами и заголовком страницы.
 * - Безопасно импортировать готовые HTML-документы, разбирая их на составные части.
 *
 * @todo При доработке резолвера (параметры по умолчанию, make) убрать жесткую зависимость в конструкторе
 */
class HtmlPageResponse extends HtmlResponse
{
    /**
     * Конструктор страницы, управляющий структурой и ресурсами.
     */
    public private(set) PageBuilder $builder;

    /**
     * Инициализирует ответ с готовым к работе билдером.
     *
     * Зависимости (Environment, Config, FileManager) извлекаются из контейнера
     * вручную, так как на текущем этапе фреймворк не поддерживает автоматический
     * резолвинг аргументов конструктора ответа.
     *
     * @param ServiceContainer $container контейнер сервисов для получения конфигурации и окружения
     */
    public function __construct(
        ServiceContainer $container,
    ) {
        /** @var PageBuilderConfig $pageBuilderConfig */
        $pageBuilderConfig = $container->get(PageBuilderConfig::class);
        /** @var CookieConfig $cookieConfig */
        $cookieConfig = $container->get(CookieConfig::class);
        /** @var Path $paths */
        $paths = $container->get(Path::class);
        $manager = new AssetFileManager($paths->basePath, $paths->publicPath, 'v');
        $this->builder = new PageBuilder($pageBuilderConfig, $manager);
        parent::__construct($cookieConfig);
    }

    /**
     * Устанавливает тело ответа с интеллектуальной обработкой HTML.
     *
     * - Если передан полноценный HTML-документ (с тегами <html>, <head> или <body>),
     *   он разбирается через HtmlImporter, и его части распределяются по билдеру.
     * - Если передан фрагмент HTML, он устанавливается как содержимое тега <body>.
     *
     * @param mixed $body Данные для установки (строка, объект с __toString и т.д.).
     *
     * @return static для поддержки цепочки вызовов
     */
    public function setBody(mixed $body): static
    {
        $html = (string) $body;
        if (HtmlImporter::isFullHtmlDocument($html)) {
            HtmlImporter::import($this->builder, $html);
        } else {
            $this->builder->setContent($html);
        }

        return $this;
    }

    /**
     * Возвращает сгенерированный HTML-код всей страницы.
     *
     * Вместо возврата сырой строки, метод делегирует генерацию билдеру,
     * который собирает head, body, подключенные ресурсы и атрибуты.
     *
     * @return string полный HTML-документ
     */
    public function getBody(): string
    {
        return $this->builder->build();
    }
}
