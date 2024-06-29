<?php
/*
Plugin Name: Easy Storage Usage Check
Plugin URI: https://github.com/itsaShane/easy-storage-usage-check
Description: Display the total storage used, the 10 largest directories, and the 50 largest files. Allows deletion of selected files, clearing the results, and exporting to CSV.
Version: 1.4
Author: ShaneW
Author URI: https://shanewalsh.ie
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: easy-storage-usage-check
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook for adding admin menus
add_action('admin_menu', 'esuc_add_admin_menu');

// Actions for the buttons
add_action('admin_post_esuc_generate_report', 'esuc_generate_report');
add_action('admin_post_esuc_delete_files', 'esuc_delete_files');
add_action('admin_post_esuc_clear_results', 'esuc_clear_results');
add_action('admin_post_esuc_download_report', 'esuc_download_report');

// Function to add the admin menu
function esuc_add_admin_menu() {
    add_menu_page(
        'Easy Storage Usage Check',
        'Storage Usage',
        'manage_options',
        'easy-storage-usage-check',
        'esuc_display_page'
    );
}

// Function to display the admin page
function esuc_display_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Easy Storage Usage Check', 'easy-storage-usage-check'); ?></h1>
        <div class="notice notice-warning">
            <p><?php esc_html_e('For security, the plugin cannot delete core WordPress files critical to its functioning.', 'easy-storage-usage-check'); ?></p>
            <?php if (is_dir(ABSPATH . '_wpeprivate')): ?>
                <p><?php esc_html_e('Files located in _wpeprivate must be deleted by WP Engine staff.', 'easy-storage-usage-check'); ?></p>
            <?php endif; ?>
        </div>
        <div class="esuc-buttons">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="esuc_generate_report">
                <?php wp_nonce_field('esuc_generate_report_action', 'esuc_generate_report_nonce'); ?>
                <?php submit_button(__('Generate Report', 'easy-storage-usage-check'), 'primary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="esuc_clear_results">
                <?php wp_nonce_field('esuc_clear_results_action', 'esuc_clear_results_nonce'); ?>
                <?php submit_button(__('Clear Results', 'easy-storage-usage-check'), 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="esuc_download_report">
                <?php wp_nonce_field('esuc_download_report_action', 'esuc_download_report_nonce'); ?>
                <?php submit_button(__('Export to CSV', 'easy-storage-usage-check'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <div id="esuc_report">
            <?php esuc_display_report(); ?>
        </div>
    </div>
    <script>
    function esuc_confirm_deletion() {
        return confirm('This is irreversible. Proceed only if you have taken a backup. Are you sure you want to delete the selected files?');
    }
    </script>
    <style>
    .esuc-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .esuc-buttons form {
        flex: 1 1 45%;
    }
    #esuc_file_list {
        max-height: 500px;
        overflow-y: scroll;
    }
    </style>
    <?php
}

// Function to generate and display the report
function esuc_generate_report() {
    if (!esuc_verify_nonce('esuc_generate_report_nonce', 'esuc_generate_report_action')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $report = esuc_calculate_storage_usage();
    set_transient('esuc_report', $report, HOUR_IN_SECONDS);

    wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
    exit;
}

// Verify nonce helper function
function esuc_verify_nonce($nonce_field, $action) {
    return isset($_POST[$nonce_field]) && wp_verify_nonce($_POST[$nonce_field], $action);
}

// Function to display the report
function esuc_display_report() {
    $report = get_transient('esuc_report');
    if ($report) {
        echo '<h2>' . esc_html__('Total Storage Used: ', 'easy-storage-usage-check') . esc_html(number_format($report['total_storage'] / 1048576, 2)) . ' MB</h2>';
        echo '<h2>' . esc_html__('Largest Directories', 'easy-storage-usage-check') . '</h2>';
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>' . esc_html__('Directory', 'easy-storage-usage-check') . '</th><th>' . esc_html__('Size (MB)', 'easy-storage-usage-check') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($report['largest_directories'] as $dir) {
            echo '<tr><td>' . esc_html($dir['name']) . '</td><td>' . esc_html(number_format($dir['size'] / 1048576, 2)) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Largest Files', 'easy-storage-usage-check') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return esuc_confirm_deletion();">';
        echo '<input type="hidden" name="action" value="esuc_delete_files">';
        echo '<table id="esuc_file_list" class="widefat fixed">';
        echo '<thead><tr><th>' . esc_html__('File', 'easy-storage-usage-check') . '</th><th>' . esc_html__('Size (MB)', 'easy-storage-usage-check') . '</th><th>' . esc_html__('Select', 'easy-storage-usage-check') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($report['largest_files'] as $file) {
            $file_path = $file['name'];
            if (esuc_can_delete_file($file_path)) {
                echo '<tr><td>' . esc_html($file['name']) . '</td><td>' . esc_html(number_format($file['size'] / 1048576, 2)) . '</td><td><input type="checkbox" name="esuc_selected_files[]" value="' . esc_attr($file['name']) . '"></td></tr>';
            } else {
                echo '<tr><td>' . esc_html($file['name']) . '</td><td>' . esc_html(number_format($file['size'] / 1048576, 2)) . '</td><td>' . esc_html__('Cannot delete', 'easy-storage-usage-check') . '</td></tr>';
            }
        }
        echo '</tbody></table>';
        wp_nonce_field('esuc_delete_files_action', 'esuc_delete_files_nonce');
        submit_button(__('Delete Selected Files', 'easy-storage-usage-check'), 'delete', 'submit', false, array('id' => 'esuc_delete_button', 'style' => 'background-color:red;color:white;'));
        echo '</form>';
    } else {
        echo '<p>' . esc_html__('No report generated yet.', 'easy-storage-usage-check') . '</p>';
    }
}

// Helper function to calculate storage usage
function esuc_calculate_storage_usage() {
    $directory = ABSPATH;
    $largest_directories = esuc_get_largest_directories($directory, 10);
    $largest_files = esuc_get_largest_files($directory, 50);
    $total_storage = esuc_get_total_storage($directory);

    return [
        'total_storage' => $total_storage,
        'largest_directories' => $largest_directories,
        'largest_files' => $largest_files
    ];
}

// Helper function to get the largest directories
function esuc_get_largest_directories($directory, $limit) {
    $dir_sizes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            $dir_path = $file->getPathname();
            try {
                $dir_size = esuc_get_directory_size($dir_path);
                $dir_sizes[] = ['name' => $dir_path, 'size' => $dir_size];
            } catch (UnexpectedValueException $e) {
                // Skip directories that cannot be read
                continue;
            }
        }
    }

    usort($dir_sizes, function ($a, $b) {
        return $b['size'] - $a['size'];
    });

    return array_slice($dir_sizes, 0, $limit);
}

// Helper function to get directory size
function esuc_get_directory_size($directory) {
    $size = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

// Helper function to get the largest files
function esuc_get_largest_files($directory, $limit) {
    $file_sizes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $file_sizes[] = ['name' => $file->getPathname(), 'size' => $file->getSize()];
        }
    }

    usort($file_sizes, function ($a, $b) {
        return $b['size'] - $a['size'];
    });

    return array_slice($file_sizes, 0, $limit);
}

// Helper function to get the total storage size
function esuc_get_total_storage($directory) {
    $total_size = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $total_size += $file->getSize();
        }
    }
    return $total_size;
}

// Function to handle file deletion
function esuc_delete_files() {
    if (!esuc_verify_nonce('esuc_delete_files_nonce', 'esuc_delete_files_action')) {
        error_log('Nonce verification failed');
        return;
    }

    if (!current_user_can('manage_options')) {
        error_log('User does not have the required capability');
        return;
    }

    if (isset($_POST['esuc_selected_files']) && is_array($_POST['esuc_selected_files'])) {
        global $wp_filesystem;

        // Initialize the WP filesystem, no more using file_exists() function
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        foreach ($_POST['esuc_selected_files'] as $file) {
            $file_path = sanitize_text_field($file);

            // Ensure the file path is within the allowed directory and not in _wpeprivate folder
            $allowed_dir = trailingslashit(ABSPATH) . 'uploads/'; // Example subdirectory
            if (strpos(realpath($file_path), realpath($allowed_dir)) !== 0 || esuc_is_core_file($file_path) || strpos($file_path, '_wpeprivate') !== false) {
                error_log('File path is outside the allowed directory, is a core file, or is within _wpeprivate folder: ' . $file_path);
                continue;
            }

            if ($wp_filesystem->exists($file_path)) {
                if ($wp_filesystem->is_writable($file_path)) {
                    // Attempt to delete using wp_delete_file
                    if (wp_delete_file($file_path)) {
                        error_log('File deleted successfully using wp_delete_file: ' . $file_path);
                    } else {
                        error_log('wp_delete_file failed: ' . $file_path);
                        // Attempt to delete using WP_Filesystem method as fallback
                        if ($wp_filesystem->delete($file_path)) {
                            error_log('File deleted successfully using WP_Filesystem: ' . $file_path);
                        } else {
                            $error = error_get_last();
                            error_log('WP_Filesystem delete failed: ' . $file_path . ' - Error: ' . esc_html($error['message']));
                        }
                    }
                } else {
                    error_log('File is not writable: ' . $file_path);
                }
            } else {
                error_log('File does not exist: ' . $file_path);
            }
        }
    } else {
        error_log('No files selected for deletion');
    }

    wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
    exit;
}

// Function to check if a file is a core WordPress file
function esuc_is_core_file($file_path) {
    $core_files = [
        'wp-config.php',
        'wp-settings.php',
        'wp-load.php',
        'wp-blog-header.php',
        'index.php',
        // Add other core files as needed
    ];

    $wp_admin_files = glob(ABSPATH . 'wp-admin/*');
    $wp_includes_files = glob(ABSPATH . 'wp-includes/*');

    $core_files = array_merge($core_files, $wp_admin_files, $wp_includes_files);

    foreach ($core_files as $core_file) {
        if (realpath($file_path) == realpath($core_file)) {
            return true;
        }
    }

    return false;
}

// Helper function to check if a file can be deleted
function esuc_can_delete_file($file_path) {
    $allowed_dir = trailingslashit(ABSPATH) . 'uploads/'; // Example subdirectory
    return strpos(realpath($file_path), realpath($allowed_dir)) === 0 && !esuc_is_core_file($file_path) && strpos($file_path, '_wpeprivate') === false;
}

// Function to clear the results
function esuc_clear_results() {
    if (!esuc_verify_nonce('esuc_clear_results_nonce', 'esuc_clear_results_action')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    delete_transient('esuc_report');

    wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
    exit;
}

// Function to handle the download report action
function esuc_download_report() {
    if (!esuc_verify_nonce('esuc_download_report_nonce', 'esuc_download_report_action')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $report = get_transient('esuc_report');

    if (!$report) {
        wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
        exit;
    }

    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    $csv_content = '';

    $csv_content .= 'Total Storage Used' . "\n";
    $csv_content .= number_format($report['total_storage'] / 1048576, 2) . ' MB' . "\n\n";

    $csv_content .= 'Largest Directories' . "\n";
    $csv_content .= 'Directory, Size (MB)' . "\n";
    foreach ($report['largest_directories'] as $dir) {
        $csv_content .= esc_html($dir['name']) . ', ' . esc_html(number_format($dir['size'] / 1048576, 2)) . "\n";
    }
    $csv_content .= "\n";

    $csv_content .= 'Largest Files' . "\n";
    $csv_content .= 'File, Size (MB)' . "\n";
    foreach ($report['largest_files'] as $file) {
        $csv_content .= esc_html($file['name']) . ', ' . esc_html(number_format($file['size'] / 1048576, 2)) . "\n";
    }

    $temp_file = wp_tempnam();
    $wp_filesystem->put_contents($temp_file, $csv_content);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="storage-usage-report.csv"');
    echo esc_html($wp_filesystem->get_contents($temp_file));

    wp_delete_file($temp_file);

    exit;
}
?>
