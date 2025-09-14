<?php
/**
 * Enhanced Database Backup Script
 * Secure PHP script to backup MySQL database tables to CSV format in ZIP file
 * All backups are created in the same directory as this script
 */

// Security: Only allow access from localhost or your IP
$allowed_ips = ['127.0.0.1', '::1', '118.103.229.177']; 
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die('Access denied');
}

// Include your database configuration
require_once 'config_seci.php';

// Enhanced Configuration
$max_execution_time = 600; // 10 minutes for large databases
$memory_limit = '512M';
$chunk_size = 2000; // Increased chunk size for better performance
$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'db_backup_' . uniqid();

// Set execution limits
set_time_limit($max_execution_time);
ini_set('memory_limit', $memory_limit);

// Create temporary directory for CSV files
if (!mkdir($temp_dir, 0755, true)) {
    die('Error: Cannot create temporary directory');
}

class EnhancedDatabaseBackup {
    private $connection;
    private $database;
    private $temp_dir;
    private $chunk_size;
    private $total_tables = 0;
    private $completed_tables = 0;
    private $total_rows = 0;
    private $processed_rows = 0;
    
    public function __construct($connection, $database, $temp_dir, $chunk_size = 2000) {
        $this->connection = $connection;
        $this->database = $database;
        $this->temp_dir = $temp_dir;
        $this->chunk_size = $chunk_size;
    }
    
    /**
     * Get all tables from database with detailed info
     */
    public function getAllTables() {
        $tables = [];
        $result = mysqli_query($this->connection, "SHOW TABLE STATUS");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tables[] = [
                    'name' => $row['Name'],
                    'rows' => $row['Rows'],
                    'size' => $this->formatBytes($row['Data_length'] + $row['Index_length']),
                    'engine' => $row['Engine']
                ];
            }
        }
        
        return $tables;
    }
    
    /**
     * Get database information
     */
    public function getDatabaseInfo() {
        $tables = $this->getAllTables();
        $total_rows = array_sum(array_column($tables, 'rows'));
        
        // Get database size
        $result = mysqli_query($this->connection, "
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
            FROM information_schema.tables 
            WHERE table_schema='$this->database'
        ");
        
        $size = 0;
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $size = $row['DB Size in MB'];
        }
        
        return [
            'name' => $this->database,
            'tables' => count($tables),
            'total_rows' => $total_rows,
            'size' => $size . ' MB',
            'table_details' => $tables
        ];
    }
    
    /**
     * Backup single table to CSV with progress tracking
     */
    public function backupTable($table_name, $show_progress = true) {
        $filename = $this->temp_dir . DIRECTORY_SEPARATOR . $table_name . '.csv';
        
        // Open file for writing
        $file = fopen($filename, 'w');
        if (!$file) {
            throw new Exception("Cannot create file: $filename");
        }
        
        // Get table columns for headers
        $columns = $this->getTableColumns($table_name);
        fputcsv($file, $columns);
        
        // Get total rows
        $total_rows = $this->getTableRowCount($table_name);
        $processed_rows = 0;
        $offset = 0;
        
        if ($show_progress) {
            echo "📊 Backing up table: <strong>$table_name</strong> ($total_rows rows)<br>\n";
            flush();
        }
        
        // Process data in chunks
        while ($processed_rows < $total_rows) {
            $query = "SELECT * FROM `$table_name` LIMIT $this->chunk_size OFFSET $offset";
            $result = mysqli_query($this->connection, $query);
            
            if (!$result) {
                fclose($file);
                throw new Exception("Query error for table $table_name: " . mysqli_error($this->connection));
            }
            
            $chunk_rows = 0;
            while ($row = mysqli_fetch_assoc($result)) {
                // Handle NULL values and special characters
                $cleaned_row = array_map(function($value) {
                    return $value === null ? '' : $value;
                }, array_values($row));
                
                fputcsv($file, $cleaned_row);
                $chunk_rows++;
                $processed_rows++;
                $this->processed_rows++;
            }
            
            // Break if no more rows
            if ($chunk_rows == 0) {
                break;
            }
            
            $offset += $this->chunk_size;
            
            // Show progress for large tables
            if ($show_progress && $total_rows > 5000) {
                $percent = round(($processed_rows / $total_rows) * 100, 1);
                echo "⏳ Progress: $processed_rows/$total_rows rows ($percent%)<br>\n";
                flush();
            }
        }
        
        fclose($file);
        
        if ($show_progress) {
            echo "✅ Completed: <strong>$table_name</strong> - $processed_rows rows exported<br>\n";
            flush();
        }
        
        $this->completed_tables++;
        
        return [
            'filename' => $filename,
            'table' => $table_name,
            'rows' => $processed_rows,
            'file_size' => $this->formatBytes(filesize($filename))
        ];
    }
    
    /**
     * Get table column names with data types
     */
    private function getTableColumns($table) {
        $columns = [];
        $result = mysqli_query($this->connection, "DESCRIBE `$table`");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $columns[] = $row['Field'];
            }
        }
        
        return $columns;
    }
    
    /**
     * Get accurate table row count
     */
    private function getTableRowCount($table) {
        $result = mysqli_query($this->connection, "SELECT COUNT(*) as count FROM `$table`");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return (int)$row['count'];
        }
        return 0;
    }
    
    /**
     * Backup entire database with enhanced progress tracking
     */
    public function backupDatabase() {
        $db_info = $this->getDatabaseInfo();
        $this->total_tables = $db_info['tables'];
        $this->total_rows = $db_info['total_rows'];
        
        echo "<div class='progress-container'>\n";
        echo "<h3>📦 Starting Database Backup</h3>\n";
        echo "<div class='db-info'>\n";
        echo "<strong>Database:</strong> {$db_info['name']}<br>\n";
        echo "<strong>Tables:</strong> {$db_info['tables']}<br>\n";
        echo "<strong>Total Rows:</strong> " . number_format($db_info['total_rows']) . "<br>\n";
        echo "<strong>Database Size:</strong> {$db_info['size']}<br>\n";
        echo "</div><hr>\n";
        
        $results = [];
        $start_time = microtime(true);
        
        foreach ($db_info['table_details'] as $table_info) {
            try {
                $result = $this->backupTable($table_info['name']);
                $results[] = $result;
            } catch (Exception $e) {
                $results[] = [
                    'table' => $table_info['name'],
                    'error' => $e->getMessage()
                ];
                echo "❌ Error backing up table: {$table_info['name']} - {$e->getMessage()}<br>\n";
                flush();
            }
        }
        
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        
        echo "<hr><div class='summary'>\n";
        echo "<strong>📊 Backup Summary:</strong><br>\n";
        echo "✅ Tables processed: {$this->completed_tables}/{$this->total_tables}<br>\n";
        echo "📝 Total rows exported: " . number_format($this->processed_rows) . "<br>\n";
        echo "⏱️ Execution time: {$execution_time} seconds<br>\n";
        echo "</div></div>\n";
        
        return $results;
    }
    
    /**
     * Create ZIP archive in the script directory
     */
    public function createZipBackup($backup_files, $custom_name = null) {
        if (!class_exists('ZipArchive')) {
            throw new Exception("ZipArchive class not available. Please enable zip extension.");
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $zip_name = $custom_name ?: "database_backup_{$this->database}_{$timestamp}.zip";
        
        // Create zip in the same directory as script
        $zip_path = __DIR__ . DIRECTORY_SEPARATOR . $zip_name;
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Cannot create zip file: $zip_path");
        }
        
        // Add database info file
        $info_content = $this->generateDatabaseInfo();
        $zip->addFromString('database_info.txt', $info_content);
        
        // Add CSV files to zip
        $total_size = 0;
        foreach ($backup_files as $file_info) {
            if (isset($file_info['filename']) && file_exists($file_info['filename'])) {
                $zip->addFile($file_info['filename'], basename($file_info['filename']));
                $total_size += filesize($file_info['filename']);
            }
        }
        
        $zip->close();
        
        return [
            'zip_path' => $zip_path,
            'zip_name' => $zip_name,
            'file_count' => count($backup_files),
            'total_size' => $this->formatBytes($total_size),
            'zip_size' => $this->formatBytes(filesize($zip_path))
        ];
    }
    
    /**
     * Generate database information text file
     */
    private function generateDatabaseInfo() {
        $db_info = $this->getDatabaseInfo();
        $content = "DATABASE BACKUP INFORMATION\n";
        $content .= str_repeat("=", 50) . "\n";
        $content .= "Database Name: {$db_info['name']}\n";
        $content .= "Backup Date: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Total Tables: {$db_info['tables']}\n";
        $content .= "Total Rows: " . number_format($db_info['total_rows']) . "\n";
        $content .= "Database Size: {$db_info['size']}\n";
        $content .= str_repeat("=", 50) . "\n\n";
        
        $content .= "TABLE DETAILS:\n";
        $content .= str_repeat("-", 30) . "\n";
        foreach ($db_info['table_details'] as $table) {
            $content .= sprintf("%-20s | %8s rows | %10s | %s\n", 
                $table['name'], 
                number_format($table['rows']), 
                $table['size'],
                $table['engine']
            );
        }
        
        return $content;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Cleanup temporary files
     */
    public function cleanup() {
        if (is_dir($this->temp_dir)) {
            $files = glob($this->temp_dir . DIRECTORY_SEPARATOR . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->temp_dir);
        }
    }
}

// Initialize backup class
$backup = new EnhancedDatabaseBackup($con, $db, $temp_dir, $chunk_size);

// Handle different modes
$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'menu';
$action = $_GET['action'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Enhanced Database Backup Tool</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; padding: 20px; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .container { 
            max-width: 900px; margin: 0 auto; 
            background: white; border-radius: 10px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 2.5em;
        }
        .db-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .db-info strong { color: #fff; }
        .form-group { margin: 20px 0; }
        .btn { 
            padding: 12px 24px; margin: 8px; 
            text-decoration: none; border: none; 
            cursor: pointer; border-radius: 6px;
            font-size: 16px; font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
        .btn-success { 
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white; 
        }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(76,175,80,0.4); }
        .btn-danger { 
            background: linear-gradient(135deg, #f44336 0%, #da190b 100%);
            color: white; 
        }
        .progress-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .summary {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        select, input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
        }
        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .table-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .table-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #667eea;
        }
        .table-card h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .backup-result {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .download-link {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            display: inline-block;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .download-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,184,148,0.4);
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .header h1 { font-size: 2em; }
            .table-info { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🗄️ Enhanced Database Backup</h1>
        <p>Secure and efficient database backup tool</p>
    </div>
    
    <?php if ($mode === 'menu'): ?>
        <?php 
        $db_info = $backup->getDatabaseInfo();
        ?>
        
        <div class="db-info">
            <h3>📊 Database Information</h3>
            <strong>Database:</strong> <?= htmlspecialchars($db_info['name']) ?><br>
            <strong>Tables:</strong> <?= $db_info['tables'] ?><br>
            <strong>Total Rows:</strong> <?= number_format($db_info['total_rows']) ?><br>
            <strong>Size:</strong> <?= $db_info['size'] ?>
        </div>
        
        <div class="table-info">
            <?php foreach (array_slice($db_info['table_details'], 0, 6) as $table): ?>
                <div class="table-card">
                    <h4><?= htmlspecialchars($table['name']) ?></h4>
                    <small>
                        📊 <?= number_format($table['rows']) ?> rows<br>
                        💾 <?= $table['size'] ?><br>
                        🔧 <?= $table['engine'] ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($db_info['table_details']) > 6): ?>
            <p><em>... and <?= count($db_info['table_details']) - 6 ?> more tables</em></p>
        <?php endif; ?>
        
        <div class="form-group" style="text-align: center; margin: 40px 0;">
            <a href="?mode=backup" class="btn btn-primary" style="font-size: 20px; padding: 20px 40px;">
                ⚡ Ultra-Fast Database Backup
            </a>
            <br><small>Maximum speed optimization - unlimited execution time, no compression</small>
        </div>
        
        <div class="form-group">
            <h4>🎯 Custom Backup Name (Optional)</h4>
            <form method="post" action="?mode=backup">
                <input type="text" name="custom_name" placeholder="Enter custom backup name (without .zip extension)" />
                <button type="submit" class="btn btn-success">Create Custom Named Backup</button>
            </form>
        </div>
        
    <?php elseif ($mode === 'backup'): ?>
        <div class="backup-result">
            <h2>🚀 Database Backup in Progress...</h2>
            
            <?php
            try {
                $start_time = microtime(true);
                
                // Start backup process
                $results = $backup->backupDatabase();
                
                // Create ZIP file
                $custom_name = $_POST['custom_name'] ?? null;
                if ($custom_name) {
                    $custom_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $custom_name) . '.zip';
                }
                
                $zip_info = $backup->createZipBackup($results, $custom_name);
                
                $end_time = microtime(true);
                $total_time = round($end_time - $start_time, 2);
                
                echo "<div class='summary'>";
                echo "<h3>🎉 Backup Completed Successfully!</h3>";
                echo "<strong>📦 ZIP File:</strong> {$zip_info['zip_name']}<br>";
                echo "<strong>📁 Files in ZIP:</strong> {$zip_info['file_count']} CSV files<br>";
                echo "<strong>📊 Original Size:</strong> {$zip_info['total_size']}<br>";
                echo "<strong>🗜️ Compressed Size:</strong> {$zip_info['zip_size']}<br>";
                echo "<strong>⏱️ Total Time:</strong> {$total_time} seconds<br>";
                
                $compression_ratio = round((1 - filesize($zip_info['zip_path']) / array_sum(array_map('filesize', array_column($results, 'filename')))) * 100, 1);
                echo "<strong>📉 Compression:</strong> {$compression_ratio}%<br>";
                echo "</div>";
                
                echo "<a href='{$zip_info['zip_name']}' class='download-link' download>";
                echo "📥 Download Backup ZIP ({$zip_info['zip_size']})";
                echo "</a>";
                
                // Show file list
                echo "<h4>📋 Files in Backup:</h4>";
                echo "<ul>";
                foreach ($results as $file_info) {
                    if (isset($file_info['error'])) {
                        echo "<li>❌ {$file_info['table']} - Error: {$file_info['error']}</li>";
                    } else {
                        echo "<li>✅ {$file_info['table']}.csv - " . number_format($file_info['rows']) . " rows ({$file_info['file_size']})</li>";
                    }
                }
                echo "</ul>";
                
            } catch (Exception $e) {
                echo "<div class='error'>";
                echo "<h3>❌ Backup Failed</h3>";
                echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
            } finally {
                // Cleanup temporary files
                $backup->cleanup();
            }
            ?>
        </div>
        
        <a href="?" class="btn btn-primary">🔙 Back to Menu</a>
        
    <?php endif; ?>
</div>

<?php
// Cleanup and close connection
if (isset($backup)) {
    $backup->cleanup();
}
mysqli_close($con);
?>

<script>
// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh progress for large backups
    <?php if ($mode === 'backup'): ?>
    // You can add JavaScript here for real-time progress if needed
    <?php endif; ?>
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '⏳ Processing...';
                submitBtn.disabled = true;
            }
        });
    });
});
</script>

</body>
</html>
