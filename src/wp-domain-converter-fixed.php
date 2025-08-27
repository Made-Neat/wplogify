<?php
/**
 * Text Domain Converter (Fixed Version)
 * 
 * This script converts all instances of 'action-scheduler' text domain to 'logify-wp'
 * in PHP files within the action-scheduler directory.
 */

// Set the base directory for Action Scheduler
$base_dir = __DIR__ . DIRECTORY_SEPARATOR . 'action-scheduler';

// Count of files modified and replacements made
$modified_files = 0;
$replacements = 0;

echo "Starting text domain conversion...\n";

/**
 * Process a single PHP file to replace text domains
 *
 * @param string $file_path Path to the file
 * @return array Number of replacements made
 */
function process_file($file_path) {
    $content = file_get_contents($file_path);
    $original = $content;
    $count = 0;
    
    // Pattern 1: Standard translation functions like __(), _e(), etc.
    $pattern = "/(__|_e|esc_attr__|esc_html__|esc_attr_e|esc_html_e)\s*\(\s*(['\"])(.+?)\\2\s*,\s*(['\"])action-scheduler\\4/s";
    $replacement = "$1($2$3$2, $4logify-wp$4";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Pattern 2: Translation functions with context
    $pattern = "/(_x|_ex)\s*\(\s*(['\"])(.+?)\\2\s*,\s*(['\"])(.+?)\\4\s*,\s*(['\"])action-scheduler\\6/s";
    $replacement = "$1($2$3$2, $4$5$4, $6logify-wp$6";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Pattern 3: Plural translation functions (with improved pattern)
    $pattern = "/(_n|_nx)\s*\(\s*(['\"])(.+?)\\2\s*,\s*(['\"])(.+?)\\4\s*,\s*(.+?)\s*,\s*(['\"])action-scheduler\\7/s";
    $replacement = "$1($2$3$2, $4$5$4, $6, $7logify-wp$7";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Pattern 3.5: _n_noop function
    $pattern = "/(_n_noop)\s*\(\s*(['\"])(.+?)\\2\s*,\s*(['\"])(.+?)\\4\s*,\s*(['\"])action-scheduler\\6/s";
    $replacement = "$1($2$3$2, $4$5$4, $6logify-wp$6";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Pattern 4: Text domain loading functions
    $pattern = "/(load_plugin_textdomain|load_textdomain)\s*\(\s*(['\"])action-scheduler\\2/s";
    $replacement = "$1($2logify-wp$2";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Pattern 5: Plugin header Text Domain
    $pattern = "/(Text Domain:)\s*action-scheduler/";
    $replacement = "$1 logify-wp";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Pattern 6: Translate with escape
    $pattern = "/(esc_html__)\s*\(\s*(['\"])(.+?)\\2\s*,\s*(['\"])action-scheduler\\4/s";
    $replacement = "$1($2$3$2, $4logify-wp$4";
    $content = preg_replace($pattern, $replacement, $content, -1, $c);
    $count += $c;
    
    // Only write to file if changes were made
    if ($original !== $content) {
        file_put_contents($file_path, $content);
        return $count;
    }
    
    return 0;
}

/**
 * Recursively process all PHP files in a directory
 * 
 * @param string $dir Directory to process
 * @global int $modified_files Counter for modified files
 * @global int $replacements Counter for replacements made
 */
function process_directory($dir) {
    global $modified_files, $replacements;
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            process_directory($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $count = process_file($path);
            
            if ($count > 0) {
                $modified_files++;
                $replacements += $count;
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a CLI script
                echo "Modified: $path ($count replacements)\n";
            }
        }
    }
}

// Start processing from the base directory
process_directory($base_dir);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a CLI script
echo "\nConversion complete!\n";
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a CLI script
echo "Modified $modified_files files with $replacements text domain replacements.\n"; 