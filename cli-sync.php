<?php
$lb = PHP_SAPI === 'cli' ? PHP_EOL : '<br>';
if(PHP_SAPI !== 'cli'){
    echo 'This script only run on CLI';exit();
}
$isForce = false;
$isDelete = false;
foreach ($argv as $arg){
    if($arg === '-f' || $arg === '--force'){
        $isForce = true;
    }
    if($arg === '-d' || $arg === '--delete'){
        $isDelete = true;
    }
}
if ( ! isset( $wp_did_header ) ) {

    $wp_did_header = true;

    // Load the WordPress library.
    require_once( __DIR__ . '/../../../' . '/wp-load.php' );

    // Set up the WordPress query.
    wp();

    // Load the theme template.
//    require_once( ABSPATH . WPINC . '/template-loader.php' );

}
/* get all attachment */
$query = 'SELECT `id` FROM `wp_posts` WHERE `post_type` = \'attachment\'';
$attIds = $wpdb->get_results($query);
$total     = $wpdb->get_var('SELECT FOUND_ROWS()');
/* end */
$process = 0;
$dosInstance = DOS::get_instance();
$upload_dir = wp_upload_dir();

echo sprintf('Total attachments count : %d%s',$total,$lb);
foreach ($attIds as $att) {
    if(!$isForce && in_array((int)$att->id,$dosInstance->getIdJson(),true)){
        echo sprintf('--- Id exists in json files. Skip attachment id : %d%s',$att->id,$lb);
        continue;
    }
    if($isForce || !in_array((int)$att->id,$dosInstance->getIdJson(),true)){
        echo sprintf('Syncing attachment id : %d%s',$att->id,$lb);
        $metadata = $dosInstance->sync($att->id);
        if ($isDelete) {
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
                    echo sprintf('+ Deleted : %s%s', $path,$lb);
                } else {
                    echo sprintf('-- Error when Deleting : %s%s', $path,$lb);
                }
            }
        }
    }


}
//$args = array('post_type'=>'attachment','order' => 'ASC','orderby' => 'id','post_per_page' => 1000,'numberposts' => 1000,'fields' => 'id');
//$qr = new WP_Query;
//$attachments = $qr->query($args);
//$attachments = get_posts($args);


//exit();
/*if($attachments){
    foreach($attachments as $attachment){
        // here your code
        echo $attachment->ID;
        echo $lb;
    }
}*/

//https://zuko.pro/wpo/wp-json/wp/v2/media?page=1&per_page=25&_fields=id&exclude_site_icons=1&orderby=id&order=asc
//var_dump(file_get_contents(site_url('wp-json/wp/v2/media?page=10&per_page=25&_fields=id&exclude_site_icons=1&orderby=id&order=asc')));
//var_dump(DOS::get_instance()->getIdJson());
//wp_update_attachment_metadata( $post_id, $data );
//var_dump(wp_count_attachments());