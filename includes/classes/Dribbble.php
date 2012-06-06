<?php
/**
 * MyDribbble Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyDribbble extends MySocial {
  private $_cacheDirPath;
  private $_cacheDirUrl;
  
  function __construct() {
    $this->service = 'Dribbble';
    $this->apiUrl = "http://api.dribbble.com/";
    $this->cacheOptionName = 'dribbble_cache';
    $this->initSettingsPage = true;
    $this->initCache( array('likes') );
    $this->_cacheDirPath = BSP_DIR_PATH . "includes/images/cache/dribbble/";
    $this->_cacheDirUrl  = BSP_DIR_URL  . "includes/images/cache/dribbble/";
  }

  protected function checkServiceError( $response ) {
    $response_body = json_decode( $response['body'] );
    if ( $response['response']['code'] != 200 ) {
      return new WP_Error( 'service_error', $response_body->message );
    }
    return $response_body;
  }
  
  function getLikesCache() {
    $this->_getLikedShots();
    return $this->cache['likes']['items'];
  }
  
  private function _getLikedShots() {
    return $this->fetchItems( 'likes', 'parseLikedShotsResponse', $this->apiUrl . 'players/bubblessoc/shots/likes?per_page=5', 60*60 );
  }
  
  function parseLikedShotsResponse( $response ) {
    $items = array();
    foreach ( $response->shots as $shot ) {
      $image = $this->_resizeShot(array(
        'id' => $shot->id,
        'url' => $shot->image_url,
        'width' => $shot->width,
        'height' => $shot->height,
        'teaser_url' => $shot->image_teaser_url,
        'cache_url' => null
      ));
      $item = array(
        'service' => strtolower($this->service),
        'url' => $shot->url,
        'image' => $image,
        'title' => $shot->title,
        'created_at' => $shot->created_at,
        'timestamp' => time(),
        'player' => array(
          'name' => $shot->player->name,
          'username' => $shot->player->username
        )
      );
      // Check if item is already cached
      $id = (string) $shot->id;
      if ( array_key_exists($id, $this->cache['likes']['items']) ) {
        $item['timestamp'] = $this->cache['likes']['items'][$id]['timestamp'];
      }
      $items[$id] = $item;
    }
    return $items;
  }
  
  /**
   * Resizes shots to square (75x75px) thumbnails, cropping from the center
   *
   * If the thumbnail already exists or was created then $image_info['cache_url']
   * will contain the the thumbnail's URL when returned. Otherwise, it will remain NULL.
   *
   * @param array $image_info contains info about the original image, including its width, height, and URL
   * @return array
   */
  private function _resizeShot( $image_info ) {
    
    // Get file extension from image url
    // Ex. http://dribbble.com/system/assets/2067/57237/screenshots/534150/bookfairy.jpg?1335480180
    $pattern = '/\/[^\.\/]+\.(png|jpg|jpeg|gif)\?\d+$/i';
    if ( preg_match( $pattern, $image_info['url'], $matches ) == 0 ) {
      return $image_info;
    }
    $extension = strtolower( $matches[1] );
    $abs_path = "{$this->_cacheDirPath}{$image_info['id']}.{$extension}";
    $rel_path = "{$this->_cacheDirUrl}{$image_info['id']}.{$extension}";
    if ( file_exists($abs_path) ) {
      $image_info['cache_url'] = $rel_path;
      return $image_info;
    }
    
    // Create local copy
    switch ( $extension ) {
      case 'png':
        $im = imagecreatefrompng( $image_info['url'] );
        break;
      case 'jpg':
      case 'jpeg':
        $im = imagecreatefromjpeg( $image_info['url'] );
        break;
      case 'gif':
        $im = imagecreatefromgif( $image_info['url'] );
        break;
      default:
        $im = false;
        break;
    }
    if ( !$im ) {
      return $image_info;
    }
    
    // Dimensions
    $ratio = floatval($image_info['width']) / floatval($image_info['height']);
    if ( $image_info['width'] < $image_info['height'] ) {
      $width = 75;
      $height = round(75 * $ratio);
      $x = 0;
      $y = floor( ($height - 75) / 2 );
    }
    elseif ( $image_info['width'] > $image_info['height'] ) {
      $width = round(75 * $ratio);
      $height = 75;
      $x = floor( ($width - 75) / 2 );
      $y = 0;
    }
    else {
      $width = $height = 75;
      $x = $y = 0;
    }
    
    // Resize image
    $thumb = imagecreatetruecolor( $width, $height );
    imagecopyresampled( $thumb, $im, 0, 0, 0, 0, $width, $height, $image_info['width'], $image_info['height'] );
    // Crop thumbnail to make square
    $square = imagecreatetruecolor( 75, 75 );
    imagecopyresampled( $square, $thumb, 0, 0, $x, $y, 75, 75, 75, 75 );
    
    switch ( $extension ) {
      case 'png':
        imagepng( $square, $abs_path );
        break;
      case 'jpg':
      case 'jpeg':
        imagejpeg( $square, $abs_path );
        break;
      case 'gif':
        imagegif( $square, $abs_path );
        break;
    }
    imagedestroy($thumb); 
  	imagedestroy($square);
  	
  	$image_info['cache_url'] = $rel_path;
    return $image_info;
  }
  
  /**
   * Deletes any thumbnails from the Dribbble cache folder
   *
   * @see MySocial::settingsResetCache()
   */
  protected function emptyCacheFolder() {
    if ( is_dir($this->_cacheDirPath) && $dh = opendir($this->_cacheDirPath) ) {
      while ( ($file = readdir($dh)) !== false ) {
        $abs_path = $this->_cacheDirPath . $file;
        if ( is_file($abs_path) && preg_match('/^[^\.]+\.(?:png|jpg|jpeg|gif)$/i', $file) == 1 && getimagesize($abs_path) )
          unlink($abs_path);
      }
      closedir($dh);
    }
  }
}
?>