<?php
/**
 * MyTumblr Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyTumblr extends MySocial_Oauth {
  function __construct() {
    $this->apiUrl = "http://api.tumblr.com/v2/";
    $this->signatures = array(
      'consumer_key'  => TUMBLR_CONSUMER_KEY,
      'shared_secret' => TUMBLR_CONSUMER_SECRET,
      'access_token'  => TUMBLR_ACCESS_TOKEN,
      'access_secret' => TUMBLR_ACCESS_TOKEN_SECRET
    );
    $this->cacheOptionName = 'tumblr_cache';
    $this->initCache( array('likes') );
  }
  
  protected function checkServiceError( $response ) {
    $response_body = json_decode( $response['body'] );
    if ( $response_body->meta->status != 200 ) {
      return new WP_Error( 'service_error', $response_body->meta->msg );
    }
    return $response_body;
  }
  
  function printPublishedPosts() {
    $result = $this->_getPublishedPosts();
    $this->printStatus($result);
    // foreach ( $this->cache['items'] as $post ) {
    //   
    // }
    var_dump($this->cache['posts']['items']);
    exit;
  }
  
  private function _getPublishedPosts() {
    $params = array(
      'api_key' => TUMBLR_CONSUMER_KEY,
      'limit' => 5
    );
    return $this->fetchItems( 'posts', 'parsePublishedPostsResponse', $this->apiUrl . 'blog/bubblessoc.tumblr.com/posts?' . http_build_query($params) );
  }
  
  function parsePublishedPostsResponse( $response ) {
    $items = array();
    foreach ( $response->response->posts as $post ) {
      // $item = array(
      //   'post_url'
      //   'date'
      //   'timestamp'
      //   ''
      // );
      array_push($items, $item);
    }
    return $items;
  }
  
  function getLikesCache() {
    $this->_getLikes();
    return $this->cache['likes']['items'];
  }
  
  private function _getLikes() {
    return $this->fetchItems( 'likes', 'parseLikesResponse', $this->getSignedURL("GET", $this->apiUrl . 'user/likes', array('limit' => 5)), 60*60 );
  }
  
  function parseLikesResponse( $response ) {
    $items = array();
    foreach ( $response->response->liked_posts as $post ) {
      // Only want photo likes
      if ( $post->type == 'photo' ) {
        $item = array(
          'service' => 'tumblr',
          'blog_name' => $post->blog_name,
          'post_url' => $post->post_url,
          'type' => $post->type,
          'date' => $post->date,
          'timestamp' => time(),
          'photos' => array()
        );
        // Parse Photos
        foreach ( $post->photos as $photo ) {
          $photo_item = array(
            'caption' => $photo->caption,
            'original_size' => (array) $photo->original_size,
            'thumbnail' => null
          );
          // Set Thumbnail
          foreach ( $photo->alt_sizes as $alt_size ) {
            if ( $alt_size->width == 75 && $alt_size->height == 75 )
              $photo_item['thumbnail'] = (array) $alt_size;
          }
          array_push($item['photos'], $photo_item);
        }
        if ( isset($post->photoset_layout) ) {
          $item['photoset_layout'] = $post->photoset_layout;
        }
        // Check if item is already cached
        $id = (string) $post->id;
        if ( array_key_exists($id, $this->cache['likes']['items']) ) {
          $item['timestamp'] = $this->cache['likes']['items'][$id]['timestamp'];
        }
        $items[$id] = $item;
      }
    }
    return $items;
  }
}
?>