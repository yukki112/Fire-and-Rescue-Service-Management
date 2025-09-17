<?php
// Check if class is already defined to prevent redeclaration
if (!class_exists('DatabaseManager')) {
    class DatabaseManager {
        private $connections = [];
        private $config = [
            'ird' => [
                'host' => 'localhost',
                'dbname' => 'frsm_2',
                'username' => 'frsm_2',
                'password' => 'Admin123'
            ],
            'frsm' => [
                'host' => 'localhost',
                'dbname' => 'frsm_1',
                'username' => 'frsm_1',
                'password' => 'Admin123'
            ],
            'ficr' => [
                'host' => 'localhost',
                'dbname' => 'frsm_7',
                'username' => 'frsm_7',
                'password' => 'Admin123'
            ],
            'fsiet' => [
                'host' => 'localhost',
                'dbname' => 'frsm_3',
                'username' => 'frsm_3',
                'password' => 'Admin123'
            ],
            'hwrm' => [
                'host' => 'localhost',
                'dbname' => 'frsm_4',
                'username' => 'frsm_4',
                'password' => 'Admin123'
            ],
            'piar' => [
                'host' => 'localhost',
                'dbname' => 'frsm_8',
                'username' => 'frsm_8',
                'password' => 'Admin123'
            ],
            'pss' => [
                'host' => 'localhost',
                'dbname' => 'frsm_5',
                'username' => 'frsm_5',
                'password' => 'Admin123'
            ],
            'tcr' => [
                'host' => 'localhost',
                'dbname' => 'frsm_6',
                'username' => 'frsm_6',
                'password' => 'Admin123'
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