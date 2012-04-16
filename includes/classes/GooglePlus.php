<?php
/**
 * MyGooglePlus Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyGooglePlus extends MySocial_Oauth {
  function __construct() {}
    
  protected function checkServiceError( $response_code, $response_body ) {
    return $response_body;
  }

  protected function parseResponse( $response ) {}
    
  function embeddedJS() {
    return <<<EOD
(function() {
  var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
  po.src = 'https://apis.google.com/js/plusone.js';
  var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
})();
EOD;
  }
  
  function shareButton( $permalink ) {
    return '<div class="g-plusone" data-size="small" data-href="'. $permalink .'"></div>';
  }
}
?>