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
   * @param array $keys Array containing the names of the sub-caches (i.e. indices)
   */
  protected function initCache( $keys ) {
    $default_cache = array_fill_keys( $keys, array('timestamp' => 0, 'items' => array()) );
    $this->cache = get_option( $this->cacheOptionName, $default_cache );
    
    // Additional keys can be added after the WordPress option is created
    // The additional keys (with default vals) should be added to the cache array
    foreach ( array_diff_key($default_cache, $this->cache) as $key => $val ) {
      $this->cache[$key] = $val;
    }
  }
  
  /**
   * Updates the cache array and WordPress option
   *
   * @uses MySocial::$cacheOptionName
   * @uses MySocial::$cache
   *
   * @param string $key
   * @param array $items
   */
  protected function updateCache( $key, $items ) {
    $this->cache[$key]['timestamp'] = time();
    $this->cache[$key]['items'] = $items;
    update_option( $this->cacheOptionName, $this->cache );
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
   *
   * @return WP_Error|array
   */
  protected function requestResource( $resource_url, $method = 'get', $args = array() ) {
    $method = strtolower($method);
    if ( $method == 'post' )
      $response = wp_remote_post( $resource_url, $args );
    else
      $response = wp_remote_get( $resource_url, $args );

    if ( is_wp_error($response) )
      return $response;
      
    return $this->checkServiceError( $response['response']['code'], json_decode($response['body']) );
  }
  
  /**
   * Uses the response code and decoded JSON to report any service-specific errors
   *
   * @param string $response_code
   * @param array $response_body Decoded JSON
   *
   * @return WP_Error|array
   */
  abstract protected function checkServiceError( $response_code, $response_body );
  
  /**
   * Prints a HTML comment containing the status of the last fetch (for debugging)
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
   * Fetches items from service and caches them if $fetch_interval seconds have passed since last cache 
   *
   * @uses MySocial::$cache
   * @uses MySocial::requestResource()
   * @uses MySocial::updateCache()
   *
   * @param string $key
   * @param string $parse_method Name of the method that parses the response
   * @param string $resource_url
   * @param int    $fetch_interval Number of seconds between each fetch
   *
   * @return WP_Error|bool Returns TRUE if no errors occurred
   */
  protected function fetchItems( $key, $parse_method, $resource_url, $fetch_interval = 60, $args = array() ) {
    $time_diff = time() - $this->cache[$key]['timestamp'];
    
    if ( $time_diff > $fetch_interval ) {
      // Fetch from Service
      $response = $this->requestResource( $resource_url, $args );
      
      if ( is_wp_error($response) )
        return $response;
      
      // Update Cache
      $this->updateCache( $key, call_user_func_array( array($this, $parse_method), array($response) ) );
    }
    // Else: Use Cache
    return true;
  }
}

abstract class MySocial_Oauth extends MySocial {
  /**
   * Hash of OAuth signatures: {api_key:, shared_secret:, oauth_token:, oauth_secret:}
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
   *
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