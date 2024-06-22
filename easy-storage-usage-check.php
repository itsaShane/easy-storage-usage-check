<?php
/*
Plugin Name: Easy Storage Usage Check
Description: A plugin to calculate storage usage, display the total storage used, the 10 largest directories, and the 50 largest files. Allows deletion of selected files, clearing the results, and exporting to CSV.
Version: 1.2
Author: ShaneW
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
            <form id="esuc_delete_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return esuc_confirm_deletion();">
                <input type="hidden" name="action" value="esuc_delete_files">
                <?php wp_nonce_field('esuc_delete_files_action', 'esuc_delete_files_nonce'); ?>
                <?php submit_button(__('Delete Selected Files', 'easy-storage-usage-check'), 'delete', 'submit', false, array('id' => 'esuc_delete_button', 'style' => 'background-color:red;color:white;')); ?>
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
    // Verify nonce
    if (!isset($_POST['esuc_generate_report_nonce']) || !wp_verify_nonce($_POST['esuc_generate_report_nonce'], 'esuc_generate_report_action')) {
        return;
    }

    // Ensure the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Calculate storage usage
    $report = esuc_calculate_storage_usage();

    // Store the report in a transient
    set_transient('esuc_report', $report, HOUR_IN_SECONDS);

    // Redirect back to the admin page
    wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
    exit;
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
        echo '<table id="esuc_file_list" class="widefat fixed">';
        echo '<thead><tr><th>' . esc_html__('File', 'easy-storage-usage-check') . '</th><th>' . esc_html__('Size (MB)', 'easy-storage-usage-check') . '</th><th>' . esc_html__('Select', 'easy-storage-usage-check') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($report['largest_files'] as $file) {
            echo '<tr><td>' . esc_html($file['name']) . '</td><td>' . esc_html(number_format($file['size'] / 1048576, 2)) . '</td><td><input type="checkbox" name="esuc_selected_files[]" value="' . esc_attr($file['name']) . '"></td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('No report generated yet.', 'easy-storage-usage-check') . '</p>';
    }
}

// Function to calculate storage usage
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
            $dir_size = esuc_get_directory_size($dir_path);
            $dir_sizes[] = ['name' => $dir_path, 'size' => $dir_size];
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
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
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
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $total_size += $file->getSize();
    }
    return $total_size;
}

// Function to handle file deletion
function esuc_delete_files() {
    // Verify nonce
    if (!isset($_POST['esuc_delete_files_nonce']) || !wp_verify_nonce($_POST['esuc_delete_files_nonce'], 'esuc_delete_files_action')) {
        return;
    }

    // Ensure the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['esuc_selected_files']) && is_array($_POST['esuc_selected_files'])) {
        foreach ($_POST['esuc_selected_files'] as $file) {
            $file_path = sanitize_text_field($file);
            if (file_exists($file_path)) {
                wp_delete_file($file_path); // Use wp_delete_file() instead of unlink()
            }
        }
    }

    // Redirect back to the admin page
    wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
    exit;
}

// Function to clear the results
function esuc_clear_results() {
    // Verify nonce
    if (!isset($_POST['esuc_clear_results_nonce']) || !wp_verify_nonce($_POST['esuc_clear_results_nonce'], 'esuc_clear_results_action')) {
        return;
    }

    // Ensure the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Delete the transient storing the report
    delete_transient('esuc_report');

    // Redirect back to the admin page
    wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
    exit;
}

// Function to handle the download report action
function esuc_download_report() {
    // Verify nonce
    if (!isset($_POST['esuc_download_report_nonce']) || !wp_verify_nonce($_POST['esuc_download_report_nonce'], 'esuc_download_report_action')) {
        return;
    }

    // Ensure the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get the report
    $report = get_transient('esuc_report');

    if (!$report) {
        wp_redirect(esc_url_raw(admin_url('admin.php?page=easy-storage-usage-check')));
        exit;
    }

    // Use WP_Filesystem API
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    // Create CSV content
    $csv_content = '';

    // Write the total storage used
    $csv_content .= 'Total Storage Used' . "\n";
    $csv_content .= number_format($report['total_storage'] / 1048576, 2) . ' MB' . "\n\n";

    // Write the largest directories
    $csv_content .= 'Largest Directories' . "\n";
    $csv_content .= 'Directory, Size (MB)' . "\n";
    foreach ($report['largest_directories'] as $dir) {
        $csv_content .= esc_html($dir['name']) . ', ' . esc_html(number_format($dir['size'] / 1048576, 2)) . "\n";
    }
    $csv_content .= "\n";

    // Write the largest files
    $csv_content .= 'Largest Files' . "\n";
    $csv_content .= 'File, Size (MB)' . "\n";
    foreach ($report['largest_files'] as $file) {
        $csv_content .= esc_html($file['name']) . ', ' . esc_html(number_format($file['size'] / 1048576, 2)) . "\n";
    }

    // Create a temporary file and write the CSV content
    $temp_file = wp_tempnam();
    $wp_filesystem->put_contents($temp_file, $csv_content);

    // Read the file content and output it for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="storage-usage-report.csv"');
    echo esc_html($wp_filesystem->get_contents($temp_file));

    // Delete the temporary file using wp_delete_file
    wp_delete_file($temp_file);

    exit;
}
?>
