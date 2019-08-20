<?php
$lb = PHP_SAPI === 'cli' ? PHP_EOL : '<br>';
if(PHP_SAPI !== 'cli'){
    echo 'This script only run on CLI';exit();
}
$keyInt = ftok(__FILE__, 'z');
$semId = sem_get($keyInt, 1);
$acquired = sem_acquire($semId, 1);
if ($acquired) {
    $isForce = false;
    $yes = false;
    foreach ($argv as $arg) {
        if ($arg === '-f' || $arg === '--force') {
            $isForce = true;
        }
        if ($arg === '-y' || $arg === '--yes') {
            $isForce = true;
        }
    }
    if (!$isForce) {
        echo 'WARNING : This script will delete all attachment files from your sever !' . $lb;
        echo 'Are you sure want to continue (yes/no) [enter to yes]' . $lb;
        $answer = strtolower(readline(''));
        if ($answer === '' || $answer === 'y' || $answer === 'yes') {
            $yes = true;
        }
    } else {
        $yes = true;
        echo 'Force set from command line' . $lb;
    }
    if (!$yes) {
        exit();
    }

    if (!isset($wp_did_header)) {

        $wp_did_header = true;

        // Load the WordPress library.
        require_once(__DIR__ . '/../../../' . '/wp-load.php');

        // Set up the WordPress query.
        wp();

        // Load the theme template.
//    require_once( ABSPATH . WPINC . '/template-loader.php' );

    }
    /* get all attachment */
    $query = 'SELECT `id` FROM `wp_posts` WHERE `post_type` = \'attachment\'';
    $attIds = $wpdb->get_results($query);
    $total = $wpdb->get_var('SELECT FOUND_ROWS()');
    /* end */
    $process = 0;
//$dosInstance = DOS::get_instance();
    $upload_dir = wp_upload_dir();

    echo sprintf('Total attachments count : %d%s', $total, $lb);
    foreach ($attIds as $att) {
        $metadata = wp_get_attachment_metadata($att->id);
        if ($metadata['image_meta']['isSynced']) {
            $paths = [];
            /* collect file paths */
            // collect original file path
            if (isset($metadata['file'])) {

                $path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'];
                $paths[] = $path;

                // set basepath for other sizes
                $file_info = pathinfo($path);
                $basepath = isset($file_info['extension'])
                    ? str_replace($file_info['filename'] . "." . $file_info['extension'], "", $path)
                    : $path;

            }

            // collect size files path
            if (isset($metadata['sizes'])) {

                foreach ($metadata['sizes'] as $size) {

                    if (isset($size['file'])) {

                        $path = $basepath . $size['file'];
                        $paths[] = $path;

                    }

                }

            }
            foreach ($paths as $path) {
                $deleted = unlink($path);
                if ($deleted) {
                    echo sprintf('+ Deleted : %s%s', $path, $lb);
                } else {
                    echo sprintf('-- Error when Deleting : %s%s', $path, $lb);
                }
            }
        } else {
            echo sprintf('Skip ID : %d -  file was not synced%s', $att->id, $lb);
        }
    }
} else {
    echo 'This script is running on another process';
    exit();
}