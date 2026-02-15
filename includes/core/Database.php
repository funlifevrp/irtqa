<?php
/**
 * طبقة قاعدة البيانات المحسنة
 * Halqat Management System
 */

class Database
{
    private static $instance = null;
    private $connection = null;
    private $config = [];
    private $transactionLevel = 0;

    private function __construct()
    {
        $this->loadConfig();
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig()
    {
        $this->config = [
            'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
            'dbname' => defined('DB_NAME') ? DB_NAME : 'halqat_db',
            'username' => defined('DB_USER') ? DB_USER : 'root',
            'password' => defined('DB_PASS') ? DB_PASS : '',
            'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        ];
    }

    private function connect()
    {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            $this->connection = new PDO($dsn, $this->config['username'], $this->config['password'], $this->config['options']);
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new Exception('فشل في الاتصال بقاعدة البيانات');
        }
    }

    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            Logger::error('Database query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('خطأ في تنفيذ الاستعلام');
        }
    }

    public function select($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function selectOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->getConnection()->lastInsertId();
    }

    public function update($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function delete($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function count($table, $conditions = [], $params = [])
    {
        $sql = "SELECT COUNT(*) as count FROM `{$table}`";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $result = $this->selectOne($sql, $params);
        return (int) $result['count'];
    }

    public function exists($table, $conditions = [], $params = [])
    {
        return $this->count($table, $conditions, $params) > 0;
    }

    public function beginTransaction()
    {
        if ($this->transactionLevel === 0) {
            $this->getConnection()->beginTransaction();
        }
        $this->transactionLevel++;
    }

    public function commit()
    {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->getConnection()->commit();
        }
    }

    public function rollback()
    {
        if ($this->transactionLevel > 0) {
            $this->getConnection()->rollback();
            $this->transactionLevel = 0;
        }
    }

    public function escape($value)
    {
        return $this->getConnection()->quote($value);
    }

    public function getLastError()
    {
        $errorInfo = $this->getConnection()->errorInfo();
        return $errorInfo[2] ?? null;
    }

    public function ping()
    {
        try {
            $this->getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function reconnect()
    {
        $this->connection = null;
        $this->connect();
    }

    public function __destruct()
    {
        $this->connection = null;
    }
}

