<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private string $viewsPath)
    {
    }

    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $templatePath = $this->resolvePath($template);
        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutPath = $this->resolvePath($layout);
        extract(array_merge($data, ['content' => $content]), EXTR_SKIP);
        ob_start();
        require $layoutPath;

        return (string) ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        $path = rtrim($this->viewsPath, '/') . '/' . ltrim($template, '/') . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException("View [{$template}] not found.");
        }

        return $path;
    }
}

