<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($viewPath)) {
            http_response_code(500);
            exit('Vue introuvable: ' . e($view));
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutPath = APP_PATH . '/Views/layouts/' . $layout . '.php';

        if (!is_file($layoutPath)) {
            http_response_code(500);
            exit('Layout introuvable: ' . e($layout));
        }

        require $layoutPath;
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}
