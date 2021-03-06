<?php

class DOS {
  
  private static $instance;
  private        $key;
  private        $secret;
  private        $endpoint;
  private        $container;
  private        $storage_path;
  private        $storage_file_only;
  private        $storage_file_delete;
  private        $filter;
  private        $upload_url_path;
  private        $upload_path;
  private        $async_upload;

  /**
   * @var \DOS_Filesystem
   * @since 2.1.1
   */
  private $fileSystem;
  private $imgInfo;
  private $_jsonIdsFile = 'dos-sync-ids.json';
  private $_dataFileName = 'dos-data.json';
	/**
	 *
	 * @return DOS
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new DOS(
				defined( 'DOS_KEY' ) ? DOS_KEY : null,
				defined( 'DOS_SECRET' ) ? DOS_SECRET : null,
        defined( 'DOS_CONTAINER' ) ? DOS_CONTAINER : null,
        defined( 'DOS_ENDPOINT' ) ? DOS_ENDPOINT : null,
        defined( 'DOS_STORAGE_PATH' ) ? DOS_STORAGE_PATH : null,
        defined( 'DOS_STORAGE_FILE_ONLY' ) ? DOS_STORAGE_FILE_ONLY : null,
        defined( 'DOS_STORAGE_FILE_DELETE' ) ? DOS_STORAGE_FILE_DELETE : null,
        defined( 'DOS_FILTER' ) ? DOS_FILTER : null,
        defined( 'UPLOAD_URL_PATH' ) ? UPLOAD_URL_PATH : null,
        defined( 'UPLOAD_PATH' ) ? UPLOAD_PATH : null
			);
		}
		return self::$instance;
  }
  
	public function __construct( $key, $secret, $container, $endpoint, $storage_path, $storage_file_only, $storage_file_delete, $filter, $upload_url_path, $upload_path ) {
		$this->key                 = empty($key) ? get_option('dos_key') : $key;
		$this->secret              = empty($secret) ? get_option('dos_secret') : $secret;
    $this->endpoint            = empty($endpoint) ? get_option('dos_endpoint') : $endpoint;
    $this->container           = empty($container) ? get_option('dos_container') : $container;
    $this->storage_path        = empty($storage_path) ? get_option('dos_storage_path') : $storage_path;
    $this->storage_file_only   = empty($storage_file_only) ? get_option('dos_storage_file_only') : $storage_file_only;
    $this->storage_file_delete = empty($storage_file_delete) ? get_option('dos_storage_file_delete') : $storage_file_delete;
    $this->filter              = empty($filter) ? get_option('dos_filter') : $filter;
    $this->upload_url_path     = ltrim(empty($upload_url_path) ? get_option('upload_url_path') : $upload_url_path,'/');
    $this->upload_path         = empty($upload_path) ? get_option('upload_path') : $upload_path;
    $this->async_upload        = defined('DOS_ASYNC_UPLOAD') ? DOS_ASYNC_UPLOAD : get_option('dos_async_upload');
    $this->fallback_url        = ltrim(defined('DOS_UPLOAD_URL_PATH_FALLBACK') ? DOS_UPLOAD_URL_PATH_FALLBACK : get_option('dos_upload_url_path_fallback'),'/');
    $dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'dos';
    if(!is_dir($dir)){
        mkdir($dir,0777);
    }
	}

  // SETUP
  public function setup () {
    $this->register_actions();
    $this->register_filters();

  }

  // REGISTRATIONS
  private function register_actions () {

    add_action('admin_menu', array($this, 'register_menu') );
    add_action('admin_init', array($this, 'register_settings' ) );
    add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts' ) );
    add_action('admin_enqueue_scripts', array($this, 'register_admin_styles' ) );
    add_action('wp_enqueue_scripts', array($this,'registerFrontendScripts') );

    add_action('wp_ajax_dos_test_connection', array($this, 'test_connection' ) );
    add_action('wp_ajax_query-attachments', array($this, 'testact' ) );
    add_action('wp_ajax_query_attachments', array($this, 'testact' ) );
    add_action('wp_ajax_dos_sync_file', array($this, 'actionSyncFileAjax' ) );
    if(!$this->async_upload){
        add_action('add_attachment', array($this, 'action_add_attachment' ), 10, 1);
    }
    add_action('delete_attachment', array($this, 'action_delete_attachment' ), 10, 1);
  }

  private function register_filters () {

    add_filter('wp_update_attachment_metadata', array($this, 'filter_wp_update_attachment_metadata'), 20, 1);
    // add_filter('wp_save_image_editor_file', array($this,'filter_wp_save_image_editor_file'), 10, 5 );
    add_filter('wp_unique_filename', array($this, 'filter_wp_unique_filename') );
    add_filter( 'wp_get_attachment_metadata', [$this,'filterWpGetAttachmentMetadata'],1,2 );
    add_filter( 'wp_get_attachment_image_attributes', [$this,'filterGetImageAttr'],1,3 );
//    add_filter( 'wp_get_attachment_image_attributes', [$this,'testact'],1,10 );
//    add_filter( 'wp_get_image', [$this,'testact'],1,10 );
//    add_filter( 'wp_get_image_tag', [$this,'testact'],1,10 );
//    add_filter( 'wp_get_image_tag_class', [$this,'testact'],1,10 );
//    add_filter( 'wp_get_post_meta', [$this,'testact'],1,10 );
  }

    public function testact()
    {
      global $post;
        $data = $this->readJson($this->_dataFileName);
        $data[] = ['testact',func_get_args(),$post];
        $this->writeJson($this->_dataFileName,$data);
        return func_get_args()[0];
  }

  public function registerFrontendScripts() {
	    $data = ['uploadUrl' => $this->upload_url_path,'fallbackUrl' => $this->fallback_url];
      wp_enqueue_script('dos-frontend-js', plugin_dir_url( __FILE__ ) . 'assets/scripts/dos-frontend.js', array('jquery'), '1.4.0', false);
      wp_localize_script( 'dos-frontend-js', 'dosData', $data );
  }

  public function register_admin_scripts () {
      $imgInfo = $this->getImgInfo();
      $totalImg = 0;
      foreach ($imgInfo as $value) {
          $totalImg += $value;
      }
      $scriptInfo = [
          'perPage' => 25,
          'curPage' => 1,
          'idList' => $this->getIdJson(),
          'totalImg' => $totalImg,
          'imgInfo' => $imgInfo,
          'processing' => [],
          'done' => $this->getIdJson(),
          'apiUrl' => site_url('wp-json/wp/v2/media?per_page=25&_fields=id&exclude_site_icons=1&orderby=id&order=asc&page='),
          'syncedImg' => 0,
          'editUrl' => site_url('wp-admin/post.php?action=edit&amp;post='),
      ];
    wp_enqueue_script('dos-core-js', plugin_dir_url( __FILE__ ) . 'assets/scripts/core.js', array('jquery'), '1.4.0', true);
    wp_localize_script( 'dos-core-js', 'dosSyncData', $scriptInfo );
  }

  public function register_admin_styles () {

    wp_enqueue_style('dos-flexboxgrid', plugin_dir_url( __FILE__ ) . 'assets/styles/flexboxgrid.min.css' );
    wp_enqueue_style('dos-progressbar', plugin_dir_url( __FILE__ ) . 'assets/styles/progressbar.css' );
    wp_enqueue_style('dos-core-css', plugin_dir_url( __FILE__ ) . 'assets/styles/core.css' );

  }

  public function register_settings () {

    register_setting('dos_settings', 'dos_key');
    register_setting('dos_settings', 'dos_secret');
    register_setting('dos_settings', 'dos_endpoint');
    register_setting('dos_settings', 'dos_container');  
    register_setting('dos_settings', 'dos_storage_path');  
    register_setting('dos_settings', 'dos_storage_file_only');
    register_setting('dos_settings', 'dos_storage_file_delete');
    register_setting('dos_settings', 'dos_filter');
    register_setting('dos_settings', 'dos_async_upload');
    register_setting('dos_settings', 'dos_upload_url_path_fallback');
    // register_setting('dos_settings', 'dos_debug');
    register_setting('dos_settings', 'upload_url_path');
    register_setting('dos_settings', 'upload_path');

  }

  public function register_setting_page () {
    include_once('dos_settings_page.php');
  }

  public function register_menu () {

    $this->setting_menu_id = add_options_page(
      'DigitalOcean Spaces Sync',
      'DigitalOcean Spaces Sync',
      'manage_options',
      'setting_page.php',
      array($this, 'register_setting_page')
    );
    $this->tool_menu_id = add_management_page(
        _x( 'Sync Existing Files to Digital Ocean Spaces', 'admin page title', 'dos-sync-existing' ),
        _x( 'Sync Existing Files to DO', 'admin menu entry title', 'dos-sync-existing' ),
        'manage_options',
        'dos-sync-existing',
        array( $this, 'register_tool_page' )
    );
  }

  /**
   * The main ajax sync interface, as displayed at Tools → Sync Existing Files to DO
   */
  public function register_tool_page() {
      global $wp_version;
      $imgInfo = $this->getImgInfo();
      $totalImg = 0;
      foreach ($imgInfo as $value) {
          $totalImg += $value;
      }
      echo '<div class="wrap">';
      echo '<h1>' . esc_html_x( 'Regenerate Thumbnails', 'admin page title', 'dos-dync' ) . '</h1>';

      if ( version_compare( $wp_version, '4.7', '<' ) ) {
          echo '<p>' . sprintf(
                  __( 'This plugin requires WordPress 4.7 or newer. You are on version %1$s. Please <a href="%2$s">upgrade</a>.', 'dos-dync' ),
                  esc_html( $wp_version ),
                  esc_url( admin_url( 'update-core.php' ) )
              ) . '</p>';
      } else {

          ?>

          <div id="regenerate-thumbnails-app">
      <div class="notice notice-error hide-if-js">
        <p><strong><?php esc_html_e( 'This tool requires that JavaScript be enabled to work.', 'regenerate-thumbnails' ); ?></strong></p>
      </div>
              <div id="dos-config">
                  <div>
                      <p><label><input type="checkbox" id="dos-regenopt-onlymissing" checked>
                              Skip sync existing file on CDN (faster).
                          </label></p>
                      <div><p><button class="button button-primary button-hero" id="dos-sync-btn">
                                  Sync All <?php echo $totalImg ?> Attachments
                              </button></p> </div> </div>
              </div>
              <div id="dos-run" style="display: none;">
                  <div data-v-44284f52="">
                      <h3 id="dos-synced-count">0 / 0</h3>
                      <div data-v-44284f52="" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" class="ui-progressbar ui-widget ui-widget-content ui-corner-all"><div class="ui-progressbar-value ui-widget-header ui-corner-left"></div></div>
                      <p data-v-44284f52="">
                          <button data-v-44284f52="" class="button button-secondary button-large" id="dos-pause-btn">
                              Pause
                          </button>
                      </p> <!---->
                      <h2 data-v-44284f52="" class="title">Regeneration Log</h2>
                      <div data-v-44284f52="">
                          <ol data-v-44284f52="" start="1" id="dos-sync-log"></ol></div></div>
              </div>
      <!--<router-view><p class="hide-if-no-js"><?php /*esc_html_e( 'Loading…', 'dos-dync' ); */?></p></router-view>-->
    </div>

          <?php

      } // version_compare()

      echo '</div>';
  }

  // FILTERS
  public function filter_wp_update_attachment_metadata ($metadata) {

    $paths = array();
    $upload_dir = wp_upload_dir();

    // collect original file path
    if ( isset($metadata['file']) ) {

      $path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'];
      array_push($paths, $path);

      // set basepath for other sizes
      $file_info = pathinfo($path);
      $basepath = isset($file_info['extension'])
          ? str_replace($file_info['filename'] . "." . $file_info['extension'], "", $path)
          : $path;

    }

    // collect size files path
    if ( isset($metadata['sizes']) ) {

      foreach ( $metadata['sizes'] as $size ) {

        if ( isset($size['file']) ) {

          $path = $basepath . $size['file'];
          array_push($paths, $path);

        }

      }

    }

    // process paths
    foreach ($paths as $filepath) {
        $this->file_upload($filepath);
    }
    $metadata['image_meta']['isSynced'] = true;
    $metadata['image_meta']['lastSync'] = date(DATE_ATOM);
    return $metadata;

  }

  public function filter_wp_unique_filename ($filename) {
    
    $upload_dir = wp_upload_dir();
    $subdir = $upload_dir['subdir'];

    $filesystem = $this->getFileSystem();

    $number = 1;
    $new_filename = $filename;
    $fileparts = pathinfo($filename);
    $cdnPath = rtrim($this->storage_path,'/') . '/' . ltrim($subdir,'/') . '/' . $new_filename;
    while ( $filesystem->has( $cdnPath ) ) {
      $new_filename = $fileparts['filename'] . "-$number." . $fileparts['extension'];
      $number = (int) $number + 1;
      $cdnPath = rtrim($this->storage_path,'/') . '/' . ltrim($subdir,'/') . '/' . $new_filename;
    }

    return $new_filename;

  }

  public function filterGetImageAttr($attrs,WP_Post $attachment,$size)
  {
      $meta = wp_get_attachment_metadata($attachment->ID);
      if(!$meta['image_meta']['isSynced']){
          foreach ($attrs as $key => $attr) {
              if(strpos($attr,$this->upload_url_path) !== false){
                  $attrs[$key] = str_replace($this->upload_url_path,$this->fallback_url,$attr);
              }
          }
      }else{
          $attrs['onerror'] = 'dosOnImgError(event)';
      }
      $attrs['data-att-id'] = $attachment->ID;
      return $attrs;
  }

  public function filterWpGetAttachmentMetadata($metadata,$attId)
  {
      if(isset($metadata['image_meta']) && !isset($metadata['image_meta']['isSynced'])){
          $metadata['image_meta']['isSynced'] = false;
      }
      return $metadata;
  }

  // ACTIONS
  public function action_add_attachment ($postID) {

    if ( wp_attachment_is_image($postID) == false ) {
  
      $file = get_attached_file($postID);
  
      $this->file_upload($file);
  
    }
  
    return true;

  }

  public function action_delete_attachment ($postID) {

    $paths = array();
    $upload_dir = wp_upload_dir();

    if ( wp_attachment_is_image($postID) == false ) {
  
      $file = get_attached_file($postID);
  
      $this->file_delete($file);
  
    } else {

      $metadata = wp_get_attachment_metadata($postID);

      // collect original file path
      if ( isset($metadata['file']) ) {

        $path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'];
        array_push($paths, $path);

        // set basepath for other sizes
        $file_info = pathinfo($path);
        $basepath = isset($file_info['extension'])
            ? str_replace($file_info['filename'] . "." . $file_info['extension'], "", $path)
            : $path;

      }

      // collect size files path
      if ( isset($metadata['sizes']) ) {

        foreach ( $metadata['sizes'] as $size ) {

          if ( isset($size['file']) ) {

            $path = $basepath . $size['file'];
            array_push($paths, $path);

          }

        }

      }

      // process paths
      foreach ($paths as $filepath) {

        // upload file
        $this->file_delete($filepath);

      }

    }

  }

  // WP ADMIN AJAX ACTIONS
  public function test_connection () {
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        $postData = $_POST;
        $keys = ['key' => 'dos_key','secret' => 'dos_secret','endpoint' => 'dos_endpoint','container' => 'dos_container'];
        foreach ($keys as $prop => $key) {
            if(isset($postData[$key])){
                $this->$prop = $postData[$key];
            }
        }
    }
    try {
    
      $filesystem = $this->getFileSystem();
      $filesystem->write('test.txt', 'test');
      $filesystem->delete('test.txt');
      // $exists = $filesystem->has('photo.jpg');

      $this->show_message(__('Connection is successfully established. Save the settings.', 'dos')); 
      exit();
  
    } catch (Exception $e) {
  
      $this->show_message( __('Connection is not established.','dos') . ' : ' . $e->getMessage() . ($e->getCode() == 0 ? '' : ' - ' . $e->getCode() ), true);
      exit();
  
    }

  }

  public function actionSyncFileAjax() {
      if($_SERVER['REQUEST_METHOD'] === 'POST'){
          $attId = $_POST['id'] ?: null;
          if(!$attId){
              echo json_encode(['ok' => false,'error' => ['code' => -10,'message' => 'Invalid attatchment id']]);
              exit();
          }
          if($_POST['skipExists']){
              $meta = wp_get_attachment_metadata($attId);
              if (isset($meta['file'])) {
                  $filesystem = $this->getFileSystem();
                  $cdnPath = rtrim($this->storage_path,'/') . '/' . ltrim($meta['file'],'/');
                  if($filesystem->has( $cdnPath )){
                      echo json_encode(['ok' => true,'error' => null,'message' => 'File Already exists on CDN','meta' => $meta]);
                      exit();
                  }
              }
          }
          $force = $_POST['isForce'] ?: false;
          $meta = $this->sync($attId,$force);
          echo json_encode(['ok' => true,'error' => null,'message' => 'Sync success','meta' => $meta]);
          exit();
      }
      echo json_encode(['ok' => false,'error' => ['code' => -10,'message' => 'Method not allowed. Must sent as post request.']]);
      exit();
  }

  //PROPERTIES GET/SET METHODS
  /**
   * @param bool $storage_file_only
   * @return DOS
   */
  public function setStorageFileOnly($storage_file_only)
  {
      $this->storage_file_only = $storage_file_only;

      return $this;
  }

  /**
   * @return array
   * @since 2.1.1
   */
  public function getIdJson() {
      return $this->readJson($this->_jsonIdsFile) ?: [];
  }

  /**
   * @param int $id
   * @since 2.1.1
   */
  public function pushIdToJson($id) {
      $id = (int)$id;
      $data = $this->readJson($this->_jsonIdsFile) ? : [];
      if (!in_array($id, $data, true)) {
          $data[] = $id;
      }
      $this->writeJson($this->_jsonIdsFile, $data);
  }

  /**
   * @return array
   */
  public function getImgInfo()
  {
      if(!$this->imgInfo){
          $this->imgInfo = get_object_vars(wp_count_attachments());
      }
      return $this->imgInfo;
  }

  private function readJson($file) {
      $dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'dos';
      $filePath = $dir . DIRECTORY_SEPARATOR . $file;
      $content = @file_get_contents($filePath);
      return json_decode($content,true);
  }

  private function writeJson($file, $data) {
      $dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'dos';
      $filePath = $dir . DIRECTORY_SEPARATOR . $file;
      $content = json_encode($data);
      if(!@file_exists($filePath)){
          @touch($filePath);
      }
      file_put_contents($filePath,$content);
  }

  //METHODS
  public function sync($attId, $isForce = false) {
      $meta = wp_get_attachment_metadata($attId);
      if ($isForce || !$meta['image_meta']['isSynced']) {
          wp_update_attachment_metadata($attId, $meta);
          $this->pushIdToJson($attId);
      }

      return wp_get_attachment_metadata($attId);
  }

  public function show_message ($message, $errormsg = false) {

    if ($errormsg) {
  
      echo '<div id="message" class="error">';
  
    } else {
  
      echo '<div id="message" class="updated fade">';
  
    }
  
    echo "<p><strong>$message</strong></p></div>";
  
  }

  // FILE METHODS
  public function file_path ($file) {

    $path = strlen($this->upload_path) ? str_replace($this->upload_path, '', $file) 
                                       : str_replace(wp_upload_dir()['basedir'], '', $file);
  
    return rtrim($this->storage_path,'/') . '/' . ltrim($path,'/');
//    return $this->storage_path . $path;

  }

    public function getFileSystem() {
        if(!$this->fileSystem instanceof DOS_Filesystem){
            $this->fileSystem = DOS_Filesystem::get_instance($this->key, $this->secret, $this->container, $this->endpoint);
        }
        return $this->fileSystem;
    }
  public function file_upload ($file) {

    // init cloud filesystem
    $filesystem = $this->getFileSystem();
    $regex_string = $this->filter;

    // prepare regex
    if ( $regex_string == '*' || !strlen($regex_string)) {
      $regex = false;
    } else {
      $regex = preg_match( $regex_string, $file);
    }

    try {

      // check if readable and regex matched
      if ( is_readable($file) && !$regex ) {

        // create fiel in storage
        $filesystem->put( $this->file_path($file), file_get_contents($file), [
          'visibility' => 'public'
        ]);

        // remove on upload
        if ( $this->storage_file_only == 1 ) {
          
          unlink($file);

        }
        
      }

      return true;

    } catch (Exception $e) {

      return false;

    }

  }

  public function file_delete ($file) {

    if ( $this->storage_file_delete == 1 ) {

      try {

        $filepath = $this->file_path($file);
        $filesystem = $this->getFileSystem();

        $filesystem->delete( $filepath );

      } catch (Exception $e) {

        error_log( $e );

      }      

    }

    return $file;   

  }

}