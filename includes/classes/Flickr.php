<?php
/**
 * MyFlickr Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyFlickr extends MySocial {
  function __construct() {
    $this->apiUrl = "http://api.flickr.com/services/rest/?api_key=" . FLICKR_API_KEY;
    $this->cacheOptionName = 'flickr_cache';
    $this->initCache();
    $this->hookAjax('bsp-print-photos', 'printPublicPhotos');
  }

  protected function checkServiceError( $response_code, $response_body ) {
    if ( $response_body->stat != 'ok' ) {
      return new WP_Error( 'service_error', $response_body->message );
    }
    return $response_body;
  }
  
  /**
   * Content-loading function for Ajax
   */
  function printPublicPhotos() {
    $result = $this->_getPublicPhotos();
    $this->printStatus($result);
    foreach ( $this->cache['items'] as $photo ) {
      $title = htmlentities( $photo['title'], ENT_QUOTES, get_bloginfo('charset') );
      echo '<li><a href="'. $photo['link'] .'" title="'. $title .' by bubblessoc, on Flickr"><img src="'. $photo['url_sq'] .'" alt="'. $title .'" width="75" height="75" /></a></li>' . "\n";
    }
    exit;
  }
  
  private function _getPublicPhotos() {
    // Ref: http://www.flickr.com/services/api/flickr.people.getPublicPhotos.html
    $params = array(
      'format'   => 'json',
      'nojsoncallback' => 1,
      'method'   => 'flickr.people.getPublicPhotos',
      'user_id'  => '13761781@N00',
      'extras'   => 'date_upload,url_sq,url_t,url_s,url_m,url_o',
      'per_page' => 5
    );
    return $this->fetchItems( $this->apiUrl . '&' . http_build_query($params) );
  }
  
  protected function parseResponse( $response ) {
    $items = array();
    foreach ( $response->photos->photo as $photo ) {
      $item = array(
        'link' => "http://www.flickr.com/photos/bubblessoc/{$photo->id}/",
        'title' => $photo->title,
        'dateupload' => $photo->dateupload,
        'url_sq' => $photo->url_sq
      );
      array_push($items, $item);
    }
    return $items;
  }
}
?>