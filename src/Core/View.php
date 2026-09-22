<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Moteur de gabarits : du PHP nu, sans couche de templating.
 *
 * Le gabarit est rendu dans un tampon, puis injecte dans le layout.
 * Toute variable affichee doit passer par View::e().
 */
final class View
{
    private static string $viewPath = '';

    public static function setViewPath(string $path): void
    {
        self::$viewPath = rtrim($path, '\/');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout/base'): string
    {
        $content = self::capture($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture($layout, $data + ['content' => $content]);
    }

    /**
     * Echappement HTML. A utiliser sur toute valeur affichee dans un gabarit.
     */
    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        $file = self::$viewPath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Gabarit introuvable : {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
