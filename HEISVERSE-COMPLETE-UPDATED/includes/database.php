<?php
declare(strict_types=1);

/**
 * HEISVERSE — Database Connection Service (PDO)
 * Provides prepared-statement-based MySQL connections.
 */

require_once __DIR__ . '/../config.php';

class Database
{
    private static ?PDO $instance = null;
    private static ?string $connectionError = null;

    /**
     * Get or create PDO singleton instance.
     */
    public static function getConnection(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
        ];

        try {
            self::$instance = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $options
            );

            self::$connectionError = null;

            return self::$instance;

        } catch (PDOException $e) {
            self::$connectionError = $e->getMessage();

            error_log('[HEISVERSE DB ERROR]: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Check whether the database connection is active.
     */
    public static function isConnected(): bool
    {
        return self::getConnection() !== null;
    }

    /**
     * Retrieve the last connection error.
     */
    public static function getLastError(): ?string
    {
        return self::$connectionError;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }
}
