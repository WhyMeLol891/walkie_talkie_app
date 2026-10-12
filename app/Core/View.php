<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class View
 * Renders PHP view templates with layouts and data extraction.
 */
class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /**
     * Render a view file with optional data and layout
     *
     * @param string $view Relative view file path without .php (e.g. 'join' or 'channel')
     * @param array<string, mixed> $data Variables to pass to the view
     * @param string|null $layout Layout file name (e.g. 'layouts/main') or null for no layout
     */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        $viewFile = self::$viewsPath . '/' . ltrim($view, '/') . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo "View file not found: " . htmlspecialchars($view);
            exit;
        }

        // Extract variables to view scope
        extract($data);

        if ($layout === null) {
            require $viewFile;
            return;
        }

        $layoutFile = self::$viewsPath . '/' . ltrim($layout, '/') . '.php';
        if (!file_exists($layoutFile)) {
            require $viewFile;
            return;
        }

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Render inside layout
        require $layoutFile;
    }
}
