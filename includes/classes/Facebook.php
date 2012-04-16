<?php
/**
 * MyFacebook Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyFacebook extends MySocial_Oauth {
  function __construct() {}
    
  protected function checkServiceError( $response_code, $response_body ) {
    return $response_body;
  }
  
  protected function parseResponse( $response ) {}
  
  function embeddedJS() {
    $app_id = FACEBOOK_APP_ID;
    return <<<EOD
window.fbAppId = {$app_id};
// Load the SDK Asynchronously
(function(d){
   var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
   if (d.getElementById(id)) {return;}
   js = d.createElement('script'); js.id = id; js.async = true;
   js.src = "//connect.facebook.net/en_US/all.js";
   ref.parentNode.insertBefore(js, ref);
 }(document));
EOD;
  }
  
  function shareButton( $permalink ) {
    return '<div class="fb-like" data-href="'. $permalink .'" data-send="false" data-layout="button_count" data-width="90" data-show-faces="false" data-font="verdana"></div>';
  }
}
?>