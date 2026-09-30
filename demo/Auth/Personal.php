<?php

declare(strict_types=1);

namespace Vasoft\Joke\Demo\Auth;

use Random\RandomException;
use Vasoft\Joke\Auth\AuthService;
use Vasoft\Joke\Auth\Session\SessionAuthenticator;
use Vasoft\Joke\Container\Exceptions\ContainerException;
use Vasoft\Joke\Container\Exceptions\ParameterResolveException;
use Vasoft\Joke\Container\Exceptions\ServiceNotFoundException;
use Vasoft\Joke\Exceptions\ConversionException;
use Vasoft\Joke\Exceptions\JokeException;
use Vasoft\Joke\Foundation\Request;
use Vasoft\Joke\Http\Csrf\CsrfTokenManager;
use Vasoft\Joke\Http\Response\RedirectResponse;
use Vasoft\Joke\Http\Response\Response;
use Vasoft\Joke\Http\Response\ResponseBuilder;

/**
 * Демонстрационный контроллер личного кабинета.
 *
 * Содержит примеры работы с аутентификацией, сессиями и CSRF-защитой.
 * Предназначен исключительно для демонстрации возможностей фреймворка
 * и не должен использоваться в продакшн-среде.
 */
class Personal
{
    public function __construct(
        private readonly ResponseBuilder $responseBuilder,
    ) {}

    /**
     * Отображает профиль текущего пользователя.
     *
     * Выводит все данные пользователя из коллекции PropsCollection
     * в формате print_r для наглядности.
     *
     * @param AuthService $auth Сервис аутентификации (внедряется автоматически)
     *
     * @return Response HTML-ответ с данными пользователя
     *
     * @throws ContainerException        При ошибках контейнера зависимостей
     * @throws ParameterResolveException При ошибках разрешения параметров
     * @throws ServiceNotFoundException  Если требуемый сервис не найден
     * @throws ConversionException       При ошибках приведения типов
     * @throws JokeException             При общих ошибках фреймворка
     */
    public function index(AuthService $auth): Response
    {
        $user = $auth->getUser();

        return $this->responseBuilder->makeDefault()->setBody(
            '<pre>' . print_r($user->data->getAll(), true) . '</pre>',
        );
    }

    /**
     * Отображает форму входа.
     *
     * Генерирует HTML-форму с CSRF-токеном для защиты от CSRF-атак.
     * Также выводит текущий токен сессии и серверный токен для отладки.
     *
     * @param Request          $request Текущий HTTP-запрос
     * @param ResponseBuilder  $builder Фабрика ответов
     * @param CsrfTokenManager $csrf    Менеджер CSRF-токенов
     *
     * @return Response HTML-ответ с формой входа
     *
     * @throws RandomException           При ошибках генерации случайных данных
     * @throws ContainerException        При ошибках контейнера зависимостей
     * @throws ParameterResolveException При ошибках разрешения параметров
     * @throws ServiceNotFoundException  Если требуемый сервис не найден
     * @throws ConversionException       При ошибках приведения типов
     * @throws JokeException             При общих ошибках фреймворка
     */
    public function login(Request $request, ResponseBuilder $builder, CsrfTokenManager $csrf): Response
    {
        $token = $csrf->getServerToken($request);
        $tokenName = CsrfTokenManager::CSRF_TOKEN_NAME;
        $sessionToken = $request->session->get(CsrfTokenManager::CSRF_TOKEN_NAME);

        return $builder->makeDefault()
            ->setBody(
                <<<HTML
                    <form method="POST" action="/personal/login">
                        <input type="hidden" name="{$tokenName}" value="{$token}">
                        <input type="text" name="login" placeholder="Логин">
                        <input type="text" name="password" placeholder="Пароль">
                        <button type="submit">Войти</button>
                    </form>
                    <p>Логин: demo, пароль: demo</p>
                    HTML,
            );
    }

    /**
     * Обрабатывает POST-запрос формы входа.
     *
     * Проверяет учетные данные (хардкод: demo/demo).
     * При успехе: сохраняет ID в сессию, сбрасывает CSRF-токен и редиректит на профиль.
     * При неудаче: возвращает форму с сообщением об ошибке.
     *
     * @param Request          $request Текущий HTTP-запрос
     * @param ResponseBuilder  $builder Фабрика ответов
     * @param CsrfTokenManager $csrf    Менеджер CSRF-токенов
     *
     * @return Response Редирект при успехе или HTML-форма с ошибкой
     *
     * @throws RandomException           При ошибках генерации случайных данных
     * @throws ContainerException        При ошибках контейнера зависимостей
     * @throws ParameterResolveException При ошибках разрешения параметров
     * @throws ServiceNotFoundException  Если требуемый сервис не найден
     * @throws ConversionException       При ошибках приведения типов
     * @throws JokeException             При общих ошибках фреймворка
     */
    public function checkLogin(Request $request, ResponseBuilder $builder, CsrfTokenManager $csrf): Response
    {
        $login = $request->post->getString('login', '');
        $password = $request->post->getString('password', '');
        $response = $builder->makeDefault();

        if ('demo' === $login && 'demo' === $password) {
            // Успешная аутентификация: сохраняем ID в сессию
            $request->session->set(SessionAuthenticator::VAR_USER_ID, 'demo');

            // Создаем редирект и сбрасываем CSRF-токен
            $response = new RedirectResponse($response->cookies, '/personal');
            $csrf->reset($request, $response);
        } else {
            // Неудачная аутентификация: показываем форму с ошибкой
            $token = $csrf->getServerToken($request);
            $tokenName = CsrfTokenManager::CSRF_TOKEN_NAME;

            $response->setBody(
                <<<HTML
                    <p>Некорректный логин или пароль</p>
                    <form method="POST" action="/personal/login">
                        <input type="hidden" name="{$tokenName}" value="{$token}">
                        <input type="text" name="login" placeholder="Логин">
                        <input type="text" name="password" placeholder="Пароль">
                        <button type="submit">Войти</button>
                    </form>
                    HTML,
            );
        }

        return $response;
    }
}
