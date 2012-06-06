<?php
/**
 * MyFlickr Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyFlickr extends MySocial {
  function __construct() {
    $this->service = 'Flickr';
    $this->apiUrl = "http://api.flickr.com/services/rest/?api_key=" . FLICKR_API_KEY . "&format=json&nojsoncallback=1";
    $this->cacheOptionName = 'flickr_cache';
    $this->initSettingsPage = true;
    $this->initCache( array('public_photos', 'favorites') );
    $this->hookAjax('bsp-print-photos', 'printPublicPhotos');
  }

  protected function checkServiceError( $response ) {
    $response_body = json_decode( $response['body'] );
    if ( $response_body->stat != 'ok' ) {
      return new WP_Error( 'service_error', $response_body->message );
    }
    return $response_body;
  }
  
  function printPublicPhotos() {
    $result = $this->_getPublicPhotos();
    $this->printStatus($result);
    foreach ( $this->cache['public_photos']['items'] as $photo ) {
      $title = htmlentities( $photo['title'], ENT_QUOTES, get_bloginfo('charset') );
      echo '<li><a href="'. $photo['link'] .'" title="'. $title .' by bubblessoc, on Flickr"><img src="'. $photo['url_sq'] .'" alt="'. $title .'" width="75" height="75" /></a></li>' . "\n";
    }
    exit;
  }
  
  private function _getPublicPhotos() {
    // Ref: http://www.flickr.com/services/api/flickr.people.getPublicPhotos.html
    $params = array(
      'method'   => 'flickr.people.getPublicPhotos',
      'user_id'  => FLICKR_USER_ID,
      'extras'   => 'date_upload,url_sq',
      'per_page' => 5
    );
    return $this->fetchItems( 'public_photos', 'parsePublicPhotosResponse', $this->apiUrl . '&' . http_build_query($params), 60*60 );
  }
  
  function parsePublicPhotosResponse( $response ) {
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
  
  function getLikesCache() {
    $this->_getFavorites();
    return $this->cache['favorites']['items'];
  }
  
  private function _getFavorites() {
    // Ref: http://www.flickr.com/services/api/flickr.favorites.getPublicList.html
    $params = array(
      'method'   => 'flickr.favorites.getPublicList',
      'user_id'  => FLICKR_USER_ID,
      'extras'   => 'owner_name,url_sq',
      'per_page' => 5
    );
    return $this->fetchItems( 'favorites', 'parseFavoritesResponse', $this->apiUrl . '&' . http_build_query($params), 60*60 );
  }
  
  private function _getUserInfo( $user_id ) {
    // Ref: http://www.flickr.com/services/api/flickr.people.getInfo.html
    $params = array(
      'method'   => 'flickr.people.getInfo',
      'user_id'  => $user_id
    );
    return $this->requestResource( $this->apiUrl . '&' . http_build_query($params) );
  }
  
  function parseFavoritesResponse( $response ) {
    $items = array();
    foreach ( $response->photos->photo as $photo ) {
      // $user_info = $this->_getUserInfo( $photo->owner );
      // if ( is_wp_error($user_info) ) {
      //   $photosurl = "http://www.flickr.com/photos/{$photo->owner}/";
      // }
      // else {
      //   $photosurl = $user_info->person->photosurl->_content;
      // }
      $item = array(
        'service' => strtolower($this->service),
        'id' => $photo->id,
        'owner_id' => $photo->owner,
        'owner_name' => $photo->ownername,
        'timestamp' => $photo->date_faved,
        'title' => $photo->title,
        'url_sq' => $photo->url_sq,
        'link' => "http://www.flickr.com/photos/{$photo->owner}/{$photo->id}"
      );
      array_push($items, $item);
    }
    return $items;
  }
}
?>