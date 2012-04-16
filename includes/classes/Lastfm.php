<?php
/**
 * MyLastfm Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */

class MyLastFM extends MySocial {
  function __construct() {
    $this->apiUrl = 'http://ws.audioscrobbler.com/2.0/?api_key=' . LASTFM_API_KEY;
    $this->cacheOptionName = 'lfm_cache';
    $this->initCache();
    $this->hookAjax('bsp-print-tracks', 'printRecentTracks');
  }

  protected function checkServiceError( $response_code, $response_body ) {
    if ( isset($response_body->error) ) {
      return new WP_Error( 'service_error', $response_body->message );
    }
    return $response_body;
  }
  
  function printRecentTracks() {
    $result = $this->_getRecentTracks();
    $this->printStatus($result);
    foreach ( $this->cache['items'] as $track ) {
      echo '<li><a href="'. $track['url'] .'"><img src="'. $track['images']['medium'] .'" alt="'. esc_attr($track['song']) . ' by ' . esc_attr($track['artist']) . ' (' . esc_attr($track['album']) .')" /></a></li>' . "\n";
    }
    exit;
  }

  private function _getRecentTracks() {
    // Ref: http://www.last.fm/api/show/user.getRecentTracks
    $params = array(
      'format'  => 'json',
      'method'  => 'user.getrecenttracks',
      'user'    => 'bubblessoc',
      'limit'   => 5
    );
    return $this->fetchItems( $this->apiUrl . '&' . http_build_query($params) );
  }
  
  protected function parseResponse( $response ) {
    $items = array();
    foreach ( $response->recenttracks->track as $track ) {
      $item['artist'] = $track->artist->{'#text'};
      $item['song'] = $track->name;
      $item['album'] = $track->album->{'#text'};
      $item['url'] = $track->url;
      $item['timestamp'] = $track->date->uts;
      foreach ( $track->image as $image ) {
        $item['images'][$image->size] = $image->{'#text'};
      }
      array_push($items, $item);
    }
    return $items;
  }
}
?>