<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur par correspondance exacte, avec support de parametres nommes : /operations/{id}.
 *
 * Un parametre capture un segment sans slash. Les valeurs extraites sont
 * passees au controleur apres la requete.
 */
final class Router
{
    /** @var array<int, array{method: string, regex: string, params: array<int, string>, handler: array{0: class-string, 1: string}}> */
    private array $routes = [];

    /**
     * Fabrique chargee de construire un controleur a partir de son nom.
     *
     * Le routeur ne sait pas de quoi un controleur a besoin, et ne doit pas le
     * savoir. Le point d'entree lui fournit cette closure, qui injecte les
     * dependances communes -- c'est de l'injection par constructeur, faite a
     * la main faute de conteneur, mais avec le meme benefice : les dependances
     * sont explicites et remplacables par des doubles dans un test.
     *
     * @var (callable(class-string): object)|null
     */
    private $resolver;

    /**
     * @param (callable(class-string): object)|null $resolver
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function add(string $method, string $path, array $handler): self
    {
        $params = [];

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function get(string $path, array $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function post(string $path, array $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * @throws NotFoundException si aucune route ne correspond.
     */
    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);
            $arguments = array_combine($route['params'], $matches) ?: [];

            [$class, $method] = $route['handler'];

            $controller = $this->resolver !== null
                ? ($this->resolver)($class)
                : new $class();

            return $controller->{$method}($request, ...$arguments);
        }

        throw new NotFoundException("Aucune route pour {$request->method} {$request->path}");
    }
}
