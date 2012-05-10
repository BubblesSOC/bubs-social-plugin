<?php
/**
 * MyFacebook Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class MyFacebook extends MySocial_Oauth {
  function __construct() {}
 
  protected function checkServiceError( $response ) {
    return json_decode( $response['body'] );
  }
  
  function embeddedJS() {
    $app_id = FACEBOOK_APP_ID;
    return <<<EOD
window.fbAppId = '{$app_id}';
(function(d){
   var js, id = 'facebook-jssdk', ref = d.getElementsByTagName('script')[0];
   if (d.getElementById(id)) {return;}
   js = d.createElement('script'); js.id = id; js.async = true;
   js.src = "//connect.facebook.net/en_US/all.js";
   ref.parentNode.insertBefore(js, ref);
 }(document));
EOD;
  }
  
  function likeButton( $permalink ) {
    return '<div class="fb-like" data-href="'. $permalink .'" data-send="false" data-layout="button_count" data-width="90" data-show-faces="false" data-font="verdana"></div>';
  }
  
  function shareButton() {
    $attrs = ogp_get_attributes();
    if ( !is_null($attrs['thumbnail']) ) {
      $picture = $attrs['thumbnail'];
    }
    elseif ( is_array($attrs['images']) ) {
      $picture = $attrs['images'][0];
    }
    else {
      $picture = $attrs['default_image'];
    }
    return '<a href="#" id="fb-share" data-link="'. $attrs['url'] .'" data-picture="'. $picture .'" data-name="'. $attrs['title'] .'" data-description="'. $attrs['description'] .'">Share</a>';
  }
  
  function metaTag() {
    return '<meta property="fb:app_id" content="'. FACEBOOK_APP_ID .'" />' . "\n";
  }
}
?>