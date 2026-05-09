<?php
/**
 * Project: Customer Data Processor
 * Requirements: Multithreading, No 3rd-party libs, Data Validation, Batch Exporting.
 */

class CustomerProcessor {
    private $pdo;
    private $startTime;
    private $batchLimit = 100000; // Requirement #7: 100k per file

    public function __construct() {
        $this->startTime = microtime(true); // Requirement #8: Tracking execution time
        $this->connectDB();
        $this->prepareTables();
    }

    private function connectDB() {
        $this->pdo = new PDO("mysql:host=db;dbname=customer_db", "root", "root", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    private function prepareTables() {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS valid_customers (id INT AUTO_INCREMENT PRIMARY KEY, first_name VARCHAR(255), last_name VARCHAR(255), city VARCHAR(255), state VARCHAR(100), zip VARCHAR(20), phone VARCHAR(50), email VARCHAR(255), ip VARCHAR(50))");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS invalid_customers LIKE valid_customers"); // Requirement #4: Separate table
    }

    private function isValid($data) {
        if (count($data) < 8) return false;
        $phone = $data[5];
        $email = $data[6];

        // Requirement #10 & Note: US Phone Format Algorithm
        $phonePattern = '/^(\+?1\s?)?(\(\d{3}\)|\d{3})[\s\-]?\d{3}[\s\-]?\d{4}$/';
        $isPhoneValid = preg_match($phonePattern, $phone);
        $isEmailValid = filter_var($email, FILTER_VALIDATE_EMAIL);

        return $isPhoneValid && $isEmailValid;
    }

    public function run($sourceFile) {
    $handle = fopen($sourceFile, "r");
    $chunkSize = 50000; 
    $pids = [];

    echo "Starting multithreaded processing...\n";

    while (!feof($handle)) {
        $batch = [];
        for ($i = 0; $i < $chunkSize && ($data = fgetcsv($handle)) !== false; $i++) {
            $batch[] = $data;
        }

        if (empty($batch)) break;

        $pid = pcntl_fork(); // Requirement #11: Multithreading
        if ($pid == -1) {
            die("Could not fork process");
        } elseif ($pid == 0) {
            $this->connectDB(); 
            $this->processBatch($batch);
            exit(0);
        } else {
            $pids[] = $pid;
        }
    }

    // Wait for all child threads to finish
    foreach ($pids as $pid) pcntl_waitpid($pid, $status);
    
    // --- ADD THIS LINE TO FIX THE ERROR ---
    $this->connectDB(); 
    // ---------------------------------------

    $this->exportResults(); // Now the connection will be active here
    $this->reportExecutionTime(); // Requirement #8
}

    private function processBatch($batch) {
        foreach ($batch as $row) {
            $table = $this->isValid($row) ? "valid_customers" : "invalid_customers";
            $stmt = $this->pdo->prepare("INSERT INTO $table (first_name, last_name, city, state, zip, phone, email, ip) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute($row);
        }
    }

    private function exportResults() {
        // Requirement #5 & #7: Exporting in batches of 100k
        $totalValid = $this->pdo->query("SELECT COUNT(*) FROM valid_customers")->fetchColumn();
        $numFiles = ceil($totalValid / $this->batchLimit);

        for ($i = 0; $i < $numFiles; $i++) {
            $offset = $i * $this->batchLimit;
            $stmt = $this->pdo->prepare("SELECT * FROM valid_customers LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $this->batchLimit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $file = fopen("valid_customers_part" . ($i + 1) . ".csv", "w");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($file, $row);
            }
            fclose($file);
        }
        echo "Exported $numFiles batch files.\n";
    }

    private function reportExecutionTime() {
        $endTime = microtime(true);
        $totalTime = round($endTime - $this->startTime, 2);
        echo "Requirement #8 - Total Process Execution Time: $totalTime seconds\n";
    }
}

$processor = new CustomerProcessor();
$processor->run('1M-customers.csv');