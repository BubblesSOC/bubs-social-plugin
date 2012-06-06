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
   * Name of the service
   *
   * @var string
   */
  protected $service;
  
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
   * Should a WordPress Settings Page be initialized for this cache?
   *
   * @var boolean
   */
  protected $initSettingsPage = false;
  
  /**
   * Initializes the cache array
   *
   * @uses MySocial::$cacheOptionName
   * @param array $keys Array containing the names of the sub-caches (i.e. indices)
   */
  protected function initCache( $keys ) {
    $default_cache = array_fill_keys( array_map('strtolower', $keys), array('timestamp' => 0, 'items' => array()) );
    $this->cache = get_option( $this->cacheOptionName, $default_cache );
    
    // In case get_option() fails to return an array
    if ( !is_array($this->cache) ) {
      $this->cache = $default_cache;
    }
    
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
   * Initializes the WordPress Settings Page section for the cache
   *
   * @uses MySocial::$initSettingsPage
   * @uses MySocial::settingsSectionContent()
   * @uses MySocial::settingsField()
   * @uses MySocial::settingsResetCache()
   * @uses Bubs_Social_Plugin::$optionGroupName
   */
  function initSettingsPage() {
    if ( !$this->initSettingsPage ) return;
    $section_id = 'bsp-reset-' . strtolower($this->service) . '-cache';
    add_settings_section( $section_id, ucfirst($this->service), array($this, 'settingsSectionContent'), BSP_PLUGIN_SLUG );
    foreach ( $this->cache as $key => $cache ) {
      $field_id = $this->cacheOptionName . '-' . $key;
      add_settings_field( $field_id, 'Reset <code>' . $key . '</code> Cache?', array($this, 'settingsField'), BSP_PLUGIN_SLUG, $section_id, array( 'key' => $key, 'timestamp' => $cache['timestamp'], 'id' => $field_id ) );
    }
    register_setting( Bubs_Social_Plugin::$optionGroupName, $this->cacheOptionName, array($this, 'settingsResetCache') );
  }
  
  /**
   * Additional content to display in the WordPress Settings Page section
   */
  function settingsSectionContent() {}
    
  /**
   * Displays the checkbox or radio buttons corresponding to a subcache on the WordPress Settings Page
   *
   * @todo  Make the timestamp display properly
   * @param array $args Set via add_settings_field() in MySocial::initSettingsPage()
   */
  function settingsField( $args ) {
    if ( strtolower($this->service) == 'dribbble' && $args['key'] == 'likes' ) {
      // 75x75px Dribbble thumbnails are created & stored locally
      echo '<input type="radio" id="' . $args['id'] . '-delete" name="' . $this->cacheOptionName . '[likes]" value="delete" />' . "\n";
      echo '<label for="' . $args['id'] . '-delete">And also delete cached thumbnails</label><br />' . "\n";
      echo '<input type="radio" id="' . $args['id'] . '-no_delete" name="' . $this->cacheOptionName . '[likes]" value="no_delete" />' . "\n";
      echo '<label for="' . $args['id'] . '-no_delete">But do not delete cached thumbnails</label><br />' . "\n";
      echo '<input type="radio" id="' . $args['id'] . '-do_nothing" name="' . $this->cacheOptionName . '[likes]" value="do_nothing" />' . "\n";
      echo '<label for="' . $args['id'] . '-do_nothing">Do not reset cache</label><br />' . "\n";
    }
    else {
      echo '<input type="checkbox" id="' . $args['id'] . '" name="' . $this->cacheOptionName . '[' . $args['key'] . ']" value="true" />' . "\n";
    }
    echo '<span class="description">Last Cached: ' . ($args['timestamp'] == 0 ? 'Never' : date( get_option('date_format') . ' ' . get_option('time_format'), $args['timestamp'] )) . '</span>' . "\n";
  }
  
  /**
   * Resets the subcache(s)
   *
   * Sanitize callback called before updating the WordPress option containing the cache.
   * If called via the WordPress Settings Page then the subcache(s) specified in $values will be reset.
   *
   * @uses Bubs_Social_Plugin::$optionGroupName
   * @param mixed $values
   */
  function settingsResetCache( $values ) {
    if ( is_admin() && isset($_POST) && isset($_POST['option_page']) && $_POST['option_page'] == Bubs_Social_Plugin::$optionGroupName && is_array($values) ) {
      foreach ( $values as $key => $val ) {
        // Here $values corresponds to the checked checkboxes and radio buttons
        // $key = subcache, $val = "delete" || "no_delete" || "do_nothing" (for Dribbble Likes) || "true" (for everything else)
        if ( isset($this->cache[$key]) && $val != 'do_nothing' ) {
          $this->cache[$key] = array( 'timestamp' => 0, 'items' => array() );
          if ( $val == 'delete' && strtolower($this->service) == 'dribbble' && $key == 'likes' )
            $this->emptyCacheFolder();
        }
      }
    }  
    return $this->cache;
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
   * @param array  $args additional arguments for the WordPress HTTP functions
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
      
    return $this->checkServiceError( $response );
  }
  
  /**
   * Uses the response to report any service-specific errors
   *
   * @param array $response
   * @return WP_Error|array
   */
  abstract protected function checkServiceError( $response );
  
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
   * @param string $method get|post
   * @param array  $args Additional arguments for the WordPress HTTP functions
   *
   * @return WP_Error|bool Returns TRUE if no errors occurred
   */
  protected function fetchItems( $key, $parse_method, $resource_url, $fetch_interval = 60, $method = 'get', $args = array() ) {
    $time_diff = time() - $this->cache[$key]['timestamp'];
    
    if ( $time_diff > $fetch_interval ) {
      // Fetch from Service
      $response = $this->requestResource( $resource_url, $method, $args );
      
      if ( is_wp_error($response) )
        return $response;
      
      // Update Cache
      $this->updateCache( $key, call_user_func_array( array($this, $parse_method), array($response) ) );
    }
    // Else: Use Cache
    return true;
  }
  
  /**
   * Escape miscellaneous unicode characters not handled by WordPress
   *
   * @param  string $str
   * @return string
   */
  protected function convertChars( $str ) {
    $new_str = '';
    $strlen = strlen($str);
    for ( $i=0; $i<$strlen; $i++ ) {
      $chars_left = $strlen - $i - 1;
      if ( $chars_left >= 2 && ord($str[$i]) == hexdec('e2') ) {
        $byte2 = $str[$i+1];
        $byte3 = $str[$i+2];
        if ( ord($byte2) == hexdec('97') ) {
          // Geometric Shapes
          if ( ord($byte3) == hexdec('a0') ) {
            // Upper Half Circle
            $new_str .= '&#9696;';
          }
          elseif ( ord($byte3) == hexdec('a1') ) {
            // Lower Half Circle
            $new_str .= '&#9697;';
          }
          elseif ( ord($byte3) == hexdec('95') ) {
            // CIRCLE WITH ALL BUT UPPER LEFT QUADRANT BLACK
            $new_str .= '&#9685;';
          }
          elseif ( ord($byte3) == hexdec('8f') ) {
            // Black Circle
            $new_str .= '&#9679;';
          }
        }
        elseif ( ord($byte2) == hexdec('99') ) {
          // Miscellaneous Symbols
          if ( ord($byte3) == hexdec('a1') ) {
            // White Heart Suit
            $new_str .= '&#9825;';
          }
          elseif ( ord($byte3) == hexdec('ab') ) {
            // Beamed Eighth Notes
            $new_str .= '&#9835;';
          }
        }
        $i+=2;
      }
      else {
        $new_str .= $str[$i];
      }
    }
    return $new_str;
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