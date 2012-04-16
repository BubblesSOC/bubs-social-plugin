<?php
/**
 * Social Abstract Classes
 *
 * Defines the shared functionality for the social (Twitter, Flickr, etc.) classes
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
abstract class MySocial {
  
  /**
   * Base URL for the service's API, including trailing slash
   *
   * @var string
   */
  protected $apiUrl;
  
  /**
   * Name of the WordPress option containing the cache
   *
   * @var string
   */
  protected $cacheOptionName;
  
  /**
   * Value of the WordPress option containing the cache
   *
   * @var array
   */
  protected $cache;
  
  /**
   * Initializes the cache array
   *
   * @uses MySocial::$cacheOptionName
   */
  protected function initCache() {
    $this->cache = get_option( $this->cacheOptionName, array( 'timestamp' => 0, 'items' => array() ) );
  }
  
  /**
   * Hooks the content-loading functions to the WordPress Ajax actions
   *
   * @param string $action
   * @param string $method_name
   */
  protected function hookAjax( $action, $method_name ) {
    add_action( 'wp_ajax_nopriv_' . $action, array($this, $method_name) );
    add_action( 'wp_ajax_' . $action, array($this, $method_name) );
  }
  
  /**
   * Updates the cache array and WordPress option
   *
   * @uses MySocial::$cacheOptionName
   * @uses MySocial::$cache
   *
   * @param array $items
   */
  protected function updateCache( $items ) {
    $this->cache['timestamp'] = time();
    $this->cache['items'] = $items;
    update_option( $this->cacheOptionName, $this->cache );
  }
  
  /**
   * Retrieves a URL using either HTTP GET or HTTP POST
   *
   * If the retrieval succeeds then the JSON response is decoded.
   *
   * @uses wp_remote_get()
   * @uses wp_remote_post()
   * @uses is_wp_error()
   * @uses MySocial::checkServiceError()
   *
   * @param string $resource_url
   * @param string $method get|post
   * @return WP_Error|array
   */
  protected function requestResource( $resource_url, $method = 'get' ) {
    $method = strtolower($method);
    if ( $method == 'get' )
      $response = wp_remote_get( $resource_url );
    elseif ( $method == 'post' )
      $response = wp_remote_post( $resource_url );

    if ( is_wp_error($response) )
      return $response;
      
    return $this->checkServiceError( $response['response']['code'], json_decode($response['body']) );
  }
  
  /**
   * Uses the response code and decoded JSON to report any service-specific errors
   *
   * @param string $response_code
   * @param array $response_body Decoded JSON
   * @return WP_Error|array
   */
  abstract protected function checkServiceError( $response_code, $response_body );
  
  /**
   * Prints a HTML comment containing the status of the last fetch (for debugging)
   *
   * @see MySocial::fetchItems()
   * @see MyFlickr::printPublicPhotos()
   *
   * @uses is_wp_error()
   * @uses WP_Error::get_error_code()
   * @uses WP_Error::get_error_message()
   *
   * @param WP_Error|bool $result
   */
  protected function printStatus( $result ) {
    echo "<!-- ";
    if ( is_wp_error($result) && $result->get_error_code() == 'service_error' ) {
      echo 'Service Error: ' . $result->get_error_message();
    }
    elseif ( is_wp_error($result) ) {
      echo 'Error: ' . $result->get_error_message();
    }
    else {
      echo "Status: OK";
    }
    echo " -->\n";
  }
  
  /**
   * Fetches items from service and caches them if a minute has passed since last cache 
   *
   * @uses MySocial::$cache
   * @uses MySocial::requestResource()
   * @uses MySocial::parseResponse()
   * @uses MySocial::updateCache()
   *
   * @param string $resource_url
   * @return WP_Error|bool Returns TRUE if no errors occurred
   */
  protected function fetchItems( $resource_url ) {
    $time_diff = time() - $this->cache['timestamp'];
    
    if ( $time_diff > 60 ) {
      // Fetch from Service
      $response = $this->requestResource( $resource_url );
      
      if ( is_wp_error($response) )
        return $response;
      
      // Update Cache
      $this->updateCache( $this->parseResponse($response) );
    }
    // Else: Fetch from Cache
    return true;
  }
  
  /**
   * Iterates through the JSON returned from the service and creates an array of the items we want to cache
   *
   * @param array $response Decoded JSON
   * @return array of items to be cached
   */
  abstract protected function parseResponse( $response );
}

abstract class MySocial_Oauth extends MySocial {
  /**
   * Hash of OAuth signatures: {api_key:, shared_secret:, oauth_token: oauth_secret:}
   *
   * @see OAuthSimple::signatures()
   * @var array
   */
  protected $signatures;
  
  /**
   * Uses jr conlin's OAuthSimple class to create a signed URL
   *
   * @see OAuthSimple
   * @uses OAuthSimple::sign()
   * @uses MySocial_Oauth::$signatures
   *
   * @param string $action GET,POST,DELETE,etc.
   * @param string $path Target service URL
   * @param array $params Optional. Hash of parameters to send to the service
   * @return string Signed URL
   */
  protected function getSignedURL( $action, $path, $params = array() ) {
    $oauth = new OAuthSimple();
    $result = $oauth->sign(array(
      'action'      => $action,
      'path'        => $path,
      'parameters'  => $params,
      'signatures'  => $this->signatures
    ));
    return $result['signed_url'];
  }
}
?>