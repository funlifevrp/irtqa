<?php
/**
 * نظام التحميل التلقائي للكلاسات
 * Halqat Management System
 */

class Autoloader
{
    private static $instance = null;
    private $directories = [];
    private $classMap = [];

    private function __construct()
    {
        $this->registerDirectories();
        spl_autoload_register([$this, 'load']);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function registerDirectories()
    {
        $basePath = dirname(dirname(__DIR__));
        
        $this->directories = [
            $basePath . '/includes/core/',
            $basePath . '/includes/helpers/',
            $basePath . '/app/controllers/',
            $basePath . '/app/models/',
            $basePath . '/app/middleware/',
            $basePath . '/app/services/',
        ];
    }

    public function load($className)
    {
        // البحث في الخريطة المحفوظة أولاً
        if (isset($this->classMap[$className])) {
            require_once $this->classMap[$className];
            return true;
        }

        // البحث في المجلدات المسجلة
        foreach ($this->directories as $directory) {
            $file = $directory . $className . '.php';
            if (file_exists($file)) {
                require_once $file;
                $this->classMap[$className] = $file;
                return true;
            }
        }

        return false;
    }

    public function addDirectory($directory)
    {
        if (is_dir($directory) && !in_array($directory, $this->directories)) {
            $this->directories[] = rtrim($directory, '/') . '/';
        }
    }

    public static function register()
    {
        return self::getInstance();
    }
}

