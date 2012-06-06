<?php
/**
 * MyTwitpic Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyTwitpic extends MySocial {
  function __construct() {
    $this->service = 'Twitpic';
    $this->apiUrl = "http://api.twitpic.com/2/";
    $this->cacheOptionName = 'twitpic_cache';
    $this->initSettingsPage = true;
    $this->initCache( array('images') );
  }

  protected function checkServiceError( $response ) {
    $response_body = json_decode( $response['body'] );
    if ( isset($response_body->errors) && is_array($response_body->errors) ) {
      return new WP_Error( 'service_error', $response_body->errors[0]->message );
    }
    return $response_body;
  }
  
  function getImagesCache() {
    $this->_getUserInfo();
    return $this->cache['images']['items'];
  }
  
  private function _getUserInfo() {
    // Ref: http://dev.twitpic.com/docs/2/users_show
    return $this->fetchItems( 'images', 'parseUserInfoResponse', $this->apiUrl . 'users/show.json?username=bubblessoc', 60 );
  }
  
  function parseUserInfoResponse( $response ) {
    $items = array();
    foreach ( $response->images as $image ) {
      $item = array(
        'service' => strtolower($this->service),
        'short_id' => $image->short_id,
        'message' => $this->convertChars( $image->message ),
        'timestamp' => $image->timestamp,
        'link' => "http://twitpic.com/{$image->short_id}",
        'thumb' => "http://twitpic.com/show/mini/{$image->short_id}"
      );
      array_push($items, $item);
    }
    return array_slice( $items, 0, 5 );
  }
}
?>