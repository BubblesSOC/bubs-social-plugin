<?php
/**
 * MyPinterest Class
 *
 * @package Bubs_Social_Plugin
 * @subpackage MyPinterest
 * @since 1.0
 */
class MyPinterest extends MySocial {
  private $_cacheDirPath;
  private $_cacheDirUrl;
  
  function __construct() {
    $this->service = 'Pinterest';
    $this->apiUrl = '';
    $this->cacheOptionName = 'pinterest_cache';
    $this->initSettingsPage = true;
    $this->widgetClassName = 'MyPinterest_Widget';
    $this->initCache( array('pins') );
    $this->hookAjax('bsp-print-pins', 'printPins');
    add_action( 'wp_enqueue_scripts', array($this, 'pinButtonJS') );
    $this->_cacheDirPath = BSP_DIR_PATH . "includes/images/cache/pinterest/";
    $this->_cacheDirUrl  = BSP_DIR_URL  . "includes/images/cache/pinterest/";
  }
  
  protected function checkServiceError( $response ) {
    libxml_use_internal_errors(true);
    $rss = new SimpleXMLElement( $response['body'] );
    if ( $response['response']['code'] != 200 ) {
      return new WP_Error( 'service_error', $response['response']['message'] );
    }
    elseif ( !$rss ) {
      return new WP_Error( 'service_error', 'Failed loading XML' );
    }
    return $rss;
  }
  
  function pinButtonJS() {
    wp_enqueue_script( 'pinterest_js', 'http://assets.pinterest.com/js/pinit.js', array('rk_wordpress_js'), false, true );
  }
  
  function printPins() {
    $result = $this->_getPinFeed();
    $this->printStatus($result);
    foreach ( $this->cache['pins']['items'] as $pin ) {
      echo '<li><a href="'. $pin['link'] .'"><img src="'. $pin['image'] .'" alt="" /></a></li>' . "\n";
    }
    exit;
  }
  
  private function _getPinFeed() {
    return $this->fetchItems( 'pins', 'parsePinFeedResponse', 'http://pinterest.com/bubblessoc/feed.rss',1 );
  }
  
  function parsePinFeedResponse( $response ) {
    $items = array();
    foreach ( $response->channel->item as $pin ) {
      preg_match( '/src="([^"]+)"/i', (string) $pin->description, $matches );
      $item = array(
        'title' => (string) $pin->title,
        'link' => (string) $pin->link,
        'pubDate' => (string) $pin->pubDate,
        'image' => $matches[1]
      );
      array_push($items, $item);
    }
    return array_slice( $items, 0, 5 );
  }
}

/**
 * MyPinterest_Widget Class
 *
 * @package Bubs_Social_Plugin
 * @subpackage MyPinterest
 * @since 1.0
 */
class MyPinterest_Widget extends MySocial_Widget {
  function __construct() {
    parent::registerWidget( 'pinterest', 'Pinterest', 'Your latest pins' );
  }
}
?>