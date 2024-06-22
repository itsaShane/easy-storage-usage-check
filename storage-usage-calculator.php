<?php
/*
Plugin Name: Storage Usage Calculator
Description: A plugin to calculate storage usage, displaying the total storage used, the 10 largest directories, and the 50 largest files.
Version: 1.0
Author: ShaneW
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook for adding admin menus
add_action('admin_menu', 'suc_add_admin_menu');

// Action for the generate button
add_action('admin_post_suc_generate_report', 'suc_generate_report');

// Action for the download button
add_action('admin_post_suc_download_report', 'suc_download_report');

// Function to add the admin menu
function suc_add_admin_menu() {
    add_menu_page(
        'Storage Usage Calculator',
        'Storage Usage',
        'manage_options',
        'storage-usage-calculator',
        'suc_display_page'
    );
}

// Function to display the admin page
function suc_display_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Storage Usage Calculator', 'storage-usage-calculator'); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="suc_generate_report">
            <?php submit_button(__('Generate Report', 'storage-usage-calculator')); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="suc_download_report">
            <?php submit_button(__('Download Report as CSV', 'storage-usage-calculator')); ?>
        </form>
        <div id="suc_report">
            <?php suc_display_report(); ?>
        </div>
    </div>
    <?php
}

// Function to generate and display the report
function suc_generate_report() {
    // Ensure the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Calculate storage usage
    $report = suc_calculate_storage_usage();

    // Store the report in a transient
    set_transient('suc_report', $report, HOUR_IN_SECONDS);

    // Redirect back to the admin page
    wp_redirect(esc_url_raw(admin_url('admin.php?page=storage-usage-calculator')));
    exit;
}

// Function to display the report
function suc_display_report() {
    $report = get_transient('suc_report');
    if ($report) {
        echo '<h2>' . esc_html__('Total Storage Used: ', 'storage-usage-calculator') . esc_html(number_format($report['total_storage'] / 1048576, 2)) . ' MB</h2>';

        echo '<h2>' . esc_html__('Largest Directories', 'storage-usage-calculator') . '</h2>';
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>' . esc_html__('Directory', 'storage-usage-calculator') . '</th><th>' . esc_html__('Size (MB)', 'storage-usage-calculator') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($report['largest_directories'] as $dir) {
            echo '<tr><td>' . esc_html($dir['name']) . '</td><td>' . esc_html(number_format($dir['size'] / 1048576, 2)) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__('Largest Files', 'storage-usage-calculator') . '</h2>';
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>' . esc_html__('File', 'storage-usage-calculator') . '</th><th>' . esc_html__('Size (MB)', 'storage-usage-calculator') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($report['largest_files'] as $file) {
            echo '<tr><td>' . esc_html($file['name']) . '</td><td>' . esc_html(number_format($file['size'] / 1048576, 2)) . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('No report generated yet.', 'storage-usage-calculator') . '</p>';
    }
}

// Function to calculate storage usage
function suc_calculate_storage_usage() {
    $directory = ABSPATH;
    $largest_directories = suc_get_largest_directories($directory, 10);
    $largest_files = suc_get_largest_files($directory, 50);
    $total_storage = suc_get_total_storage($directory);

    return [
        'total_storage' => $total_storage,
        'largest_directories' => $largest_directories,
        'largest_files' => $largest_files
    ];
}

// Helper function to get the largest directories
function suc_get_largest_directories($directory, $limit) {
    $dir_sizes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            $dir_path = $file->getPathname();
            $dir_size = suc_get_directory_size($dir_path);
            $dir_sizes[] = ['name' => $dir_path, 'size' => $dir_size];
        }
    }

    usort($dir_sizes, function ($a, $b) {
        return $b['size'] - $a['size'];
    });

    return array_slice($dir_sizes, 0, $limit);
}

// Helper function to get directory size
function suc_get_directory_size($directory) {
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

// Helper function to get the largest files
function suc_get_largest_files($directory, $limit) {
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
function suc_get_total_storage($directory) {
    $total_size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $total_size += $file->getSize();
    }
    return $total_size;
}

// Function to handle the download report action
function suc_download_report() {
    // Ensure the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get the report
    $report = get_transient('suc_report');

    if (!$report) {
        wp_redirect(esc_url_raw(admin_url('admin.php?page=storage-usage-calculator')));
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