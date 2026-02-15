<?php
/**
 * نظام تسجيل الأحداث والأخطاء
 * Halqat Management System
 */

class Logger
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    private static $logPath = null;
    private static $maxFileSize = 10485760; // 10MB
    private static $maxFiles = 5;

    public static function init()
    {
        if (self::$logPath === null) {
            self::$logPath = dirname(dirname(dirname(__FILE__))) . '/storage/logs/';
            
            // إنشاء مجلد اللوجات إذا لم يكن موجوداً
            if (!is_dir(self::$logPath)) {
                mkdir(self::$logPath, 0755, true);
            }
        }
    }

    public static function log($level, $message, $context = [])
    {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        $logFile = self::$logPath . date('Y-m-d') . '.log';
        
        // تدوير الملفات إذا تجاوز الحد الأقصى
        self::rotateLogFile($logFile);
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // إرسال إلى error_log في حالة الأخطاء الحرجة
        if (in_array($level, [self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR])) {
            error_log($logEntry);
        }
    }

    public static function emergency($message, $context = [])
    {
        self::log(self::EMERGENCY, $message, $context);
    }

    public static function alert($message, $context = [])
    {
        self::log(self::ALERT, $message, $context);
    }

    public static function critical($message, $context = [])
    {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::log(self::ERROR, $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function notice($message, $context = [])
    {
        self::log(self::NOTICE, $message, $context);
    }

    public static function info($message, $context = [])
    {
        self::log(self::INFO, $message, $context);
    }

    public static function debug($message, $context = [])
    {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::log(self::DEBUG, $message, $context);
        }
    }

    private static function rotateLogFile($logFile)
    {
        if (!file_exists($logFile)) {
            return;
        }

        if (filesize($logFile) > self::$maxFileSize) {
            // نقل الملفات القديمة
            for ($i = self::$maxFiles - 1; $i > 0; $i--) {
                $oldFile = $logFile . '.' . $i;
                $newFile = $logFile . '.' . ($i + 1);
                
                if (file_exists($oldFile)) {
                    if ($i === self::$maxFiles - 1) {
                        unlink($oldFile); // حذف أقدم ملف
                    } else {
                        rename($oldFile, $newFile);
                    }
                }
            }
            
            // نقل الملف الحالي
            rename($logFile, $logFile . '.1');
        }
    }

    public static function getLogFiles()
    {
        self::init();
        
        $files = glob(self::$logPath . '*.log*');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files;
    }

    public static function readLog($filename, $lines = 100)
    {
        self::init();
        
        $filepath = self::$logPath . $filename;
        
        if (!file_exists($filepath)) {
            return [];
        }
        
        $file = new SplFileObject($filepath);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        
        $logLines = [];
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logLines[] = $line;
            }
            $file->next();
        }
        
        return $logLines;
    }

    public static function clearOldLogs($days = 30)
    {
        self::init();
        
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $files = glob(self::$logPath . '*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
}

