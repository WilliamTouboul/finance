<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base des controleurs : rend une vue ou redirige.
 */
abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(
        string $template,
        array $data = [],
        int $status = 200,
        string $layout = 'layout/base',
    ): Response {
        $data += [
            'appName'   => (string) Config::get('app_name', 'Finance'),
            'pageTitle' => null,
        ];

        return Response::html(View::render($template, $data, $layout), $status);
    }

    protected function redirect(string $location): Response
    {
        return Response::redirect($location);
    }
}
