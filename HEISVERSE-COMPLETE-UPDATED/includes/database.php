<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class Database
{
    private static ?PDO $instance = null;
    private static ?string $connectionError = null;

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

        // Enable verified TLS when a CA certificate is configured.
        $sslCa = getenv('DB_SSL_CA');

        if ($sslCa !== false && $sslCa !== '') {
            if (!is_file($sslCa) || !is_readable($sslCa)) {
                self::$connectionError = 'Database CA certificate is missing or unreadable.';
                error_log('[HEISVERSE DB ERROR]: ' . self::$connectionError);
                return null;
            }

            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        }

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

    public static function isConnected(): bool
    {
        return self::getConnection() !== null;
    }

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
