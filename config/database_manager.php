<?php
// Check if class is already defined to prevent redeclaration
if (!class_exists('DatabaseManager')) {
    class DatabaseManager {
        private $connections = [];
        private $config = [
            'ird' => [
                'host' => 'localhost:3307',
                'dbname' => 'ird',
                'username' => 'root',
                'password' => ''
            ],
            'frsm' => [
                'host' => 'localhost:3307',
                'dbname' => 'frsm',
                'username' => 'root',
                'password' => ''
            ],
            'ficr' => [
                'host' => 'localhost:3307',
                'dbname' => 'ficr',
                'username' => 'root',
                'password' => ''
            ],
            'fsiet' => [
                'host' => 'localhost:3307',
                'dbname' => 'fsiet',
                'username' => 'root',
                'password' => ''
            ],
            'hwrm' => [
                'host' => 'localhost:3307',
                'dbname' => 'hwrm',
                'username' => 'root',
                'password' => ''
            ],
            'piar' => [
                'host' => 'localhost:3307',
                'dbname' => 'piar',
                'username' => 'root',
                'password' => ''
            ],
            'pss' => [
                'host' => 'localhost:3307',
                'dbname' => 'pss',
                'username' => 'root',
                'password' => ''
            ],
            'tcr' => [
                'host' => 'localhost:3307',
                'dbname' => 'tcr',
                'username' => 'root',
                'password' => ''
            ]
        ];

        public function getConnection($database) {
            if (!isset($this->connections[$database])) {
                if (!isset($this->config[$database])) {
                    throw new Exception("Database configuration for '$database' not found.");
                }

                $config = $this->config[$database];
                try {
                    $this->connections[$database] = new PDO(
                        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
                        $config['username'],
                        $config['password']
                    );
                    $this->connections[$database]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $this->connections[$database]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    $this->connections[$database]->exec("SET time_zone = '+08:00'");
                } catch (PDOException $e) {
                    error_log("Database connection failed for $database: " . $e->getMessage());
                    throw $e;
                }
            }

            return $this->connections[$database];
        }

        public function query($database, $query, $params = []) {
            $pdo = $this->getConnection($database);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        }

        public function fetchAll($database, $query, $params = []) {
            $stmt = $this->query($database, $query, $params);
            return $stmt->fetchAll();
        }

        public function fetch($database, $query, $params = []) {
            $stmt = $this->query($database, $query, $params);
            return $stmt->fetch();
        }
    }
}

// Create a global instance only if it doesn't exist
if (!isset($dbManager)) {
    $dbManager = new DatabaseManager();
}
?>