<?php
/*
Plugin Name: Bubs' Social Plugin
Plugin URI: http://bubblessoc.net
Description: A brief description of the Plugin.
Version: 1.0
Author: Bubs
Author URI: http://bubblessoc.net
*/

require_once('includes/oauth_keys.php');  // Service-related constants defined here
require_once('includes/classes/OAuthSimple.php');
require_once('includes/classes/MySocial.php');
require_once('includes/classes/Facebook.php');
require_once('includes/classes/Flickr.php');
require_once('includes/classes/Github.php');
require_once('includes/classes/GooglePlus.php');
require_once('includes/classes/Lastfm.php');
require_once('includes/classes/Twitter.php');
define('BSP_PLUGIN_SLUG', "bubs-social-plugin");

/**
 * BSP (Bubs' Social Plugin) Base Class
 *
 * Sets up the plugin's general WordPress functionality and instantiates the social classes.
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class Bubs_Social_Plugin {
  private $_myTwitter;
  private $_myFacebook;
  private $_myGooglePlus;
  private $_myFlickr;
  private $_myLastfm;
  private $_myGithub;
  
  function __construct() {
    $this->_myTwitter = new MyTwitter();
    $this->_myFacebook = new MyFacebook();
    $this->_myGooglePlus = new MyGooglePlus();
    $this->_myFlickr = new MyFlickr();
    $this->_myLastfm = new MyLastFM();
    $this->_myGithub = new MyGithub();
    
    // Only add admin CSS & JS to admin post pages
    if ( is_admin() && (basename($_SERVER['PHP_SELF']) == 'post.php' || basename($_SERVER['PHP_SELF']) == 'post-new.php') ) {
      add_action('admin_enqueue_scripts', array($this, 'adminJS'));
      add_action('admin_print_styles', array($this, 'adminCSS'));
    }
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'actionLinks'));
    add_action('wp_enqueue_scripts', array($this, 'socialJS'));
    add_action('wp_footer', array($this, 'embeddedJS'));

    // Comment via Social
    add_filter('wp_get_current_commenter', array($this, 'social_getCurrentCommenter'));
    add_action('comment_form_top', array($this, 'commentForm_socialFields'), 9);
  	add_action('comment_form_before_fields', array($this, 'commentForm_identities'), 11);
  	add_filter('get_avatar', array($this, 'social_getAvatar'), 10, 5);
  	add_filter('get_comment_author_link', array($this, 'social_getCommentAuthorLink'));
  	add_action('comment_post', array($this, 'commentViaSocial'), 10, 2);
  	
  	// Social Sharing
  	add_action('bsp_share_buttons', array($this, 'socialSharing'), 10, 2);
  }
  
  function socialJS() {
    // wp_enqueue_script('jquery_cookie', plugins_url('/includes/jquery.cookie.js', __FILE__), array('jquery'), false, true);
    // wp_enqueue_script('bsp_social_js', plugins_url('/includes/bsp-social.js', __FILE__), array('jquery', 'jquery_cookie', 'rk_wpcomments_js'), false, true);
    wp_enqueue_script('bsp_social_js', plugins_url('/includes/js/bsp-social.js', __FILE__), array('jquery', 'rk_wpcomments_js'), false, true);
  }
  
  /**
   * Load the services' JS for client-side functionality
   */
  function embeddedJS() {
    echo <<<EOD
<script type="text/javascript">
{$this->_myTwitter->embeddedJS()}
{$this->_myFacebook->embeddedJS()}
{$this->_myGooglePlus->embeddedJS()}
</script>
EOD;
  }
  
  function adminJS() {
    wp_enqueue_script('bsp_admin_js', plugins_url('/includes/js/bsp-admin.js', __FILE__), array('jquery'));
  }

  function adminCSS() {
    wp_enqueue_style('bsp_admin_css', plugins_url('/includes/bsp-admin.css', __FILE__));
  }
  
  function actionLinks( $actions ) {
    $actions[] = '<a href="options-general.php?page='. BSP_PLUGIN_SLUG .'">Settings</a>';
    return $actions;
  }
  
  /**
   * Add social info (service, name, profile image, profile link) as comment meta
   *
   * Action Hook: 
   * <code>
   * do_action('comment_post', $comment_ID, $commentdata['comment_approved']);
   * </code>
   *
   * @link http://www.soapboxdave.com/2010/02/using-wordpress-comment-meta/
   * @link http://pmg.co/adding-extra-fields-to-wordpress-comments
   */
  function commentViaSocial( $comment_id, $comment_approved ) {
    if ( isset($_POST['social']['comment_via']) && $_POST['social']['comment_via'] != "wordpress" ) {
      add_comment_meta( $comment_id, 'comment_via_social', $_POST['social'], true );
    }
  }
  
  /**
   * Replace Gravatar with profile photo
   *
   * Filter Hook: 
   * <code>
   * apply_filters('get_avatar', $avatar, $id_or_email, $size, $default, $alt);
   * </code>
   *
   * @see get_avatar()  wp-includes/pluggable.php
   */
  function social_getAvatar( $avatar, $id_or_email, $size, $default, $alt ) {
    if ( is_object($id_or_email) && isset($id_or_email->comment_ID) ) {
      $social = get_comment_meta( $id_or_email->comment_ID, 'comment_via_social', true );
      if ( is_array($social) ) {
        $avatar = preg_replace( "/src='.+?'/", "src='{$social['profile_image']}'", $avatar );
        $avatar = preg_replace( "/class='(.+?)'/", "class='$1 avatar-social'", $avatar );
      }
    }
    return $avatar;
  }
  
  /**
   * Add 'via service_name' after commenter's name
   *
   * Filter Hook: 
   * <code>
   * apply_filters('get_comment_author_link', $return);
   * </code>
   */
  function social_getCommentAuthorLink( $return ) {
    if ( in_the_loop() ) {
      $social = get_comment_meta( get_comment_ID(), 'comment_via_social', true );
      if ( is_array($social) ) {
        $return .= ' (via ' . ucfirst($social['comment_via']) . ')';
      }
    }
    return $return;
  }
  
  /**
   * Ignore commenter cookies set for social (don't want social info auto-filled in comment form)
   *
   * Filter Hook: 
   * <code>
   * return apply_filters('wp_get_current_commenter', compact('comment_author', 'comment_author_email', 'comment_author_url'));
   * </code>
   *
   * @see wp_get_current_commenter()  wp-includes/comment.php
   */
  function social_getCurrentCommenter( $commenter ) {
    if ( $commenter['comment_author_email'] == 'social@bubblessoc.net' ) {
      $commenter['comment_author'] = '';
      $commenter['comment_author_email'] = '';
      $commenter['comment_author_url'] = '';
    }
    return $commenter;
  }
  
  /**
   * Add the social fields to the comment form
   */
  function commentForm_socialFields() {
    echo <<<EOD
<input type="hidden" name="social[name]" id="social-name-field" value="" disabled />
<input type="hidden" name="social[profile_link]" id="social-link-field" value="" disabled />
<input type="hidden" name="social[profile_image]" id="social-image-field" value="" disabled />   
EOD;
  }

  /**
   * Add the 'comment via' radio buttons to the comment form
   */
  function commentForm_identities() {
    $default_img = plugin_dir_url( __FILE__ ) . "includes/images/mystery-man.png";
    echo <<<EOD
<li class="comment-form-field" id="comment-form-identities">
  <label>Comment via</label>
  <ul>
    <li>
      <input type="radio" id="comment-via-wordpress" name="social[comment_via]" value="wordpress" checked />
      <label for="comment-via-wordpress">WordPress</label>
    <li>
      <a href="#" id="twitter-connect">Connect with Twitter</a>
      <div id="twitter-userinfo" class="social-userinfo">
        <input type="radio" id="comment-via-twitter" name="social[comment_via]" value="twitter" />
        <img src="{$default_img}" alt="" id="twitter-profile-image" />
        <a href="#" id="twitter-profile-link"></a>
        (<a href="#" id="twitter-logout">Logout</a>)
      </div>
    </li>
    <li>
      <a href="#" id="facebook-connect">Connect with Facebook</a>
      <div id="facebook-userinfo" class="social-userinfo">
        <input type="radio" id="comment-via-facebook" name="social[comment_via]" value="facebook" />
        <img src="{$default_img}" alt="" id="facebook-profile-image" />
        <a href="#" id="facebook-profile-link"></a>
        (<a href="#" id="facebook-logout">Logout</a>)
      </div>
    </li>
  </ul>
</li>
EOD;
  }
  
  /**
   * Share Buttons
   *
   * Displayed via the following custom hook:
   * <code>
   * do_action('bsp_share_buttons', get_permalink(), wp_get_shortlink());
   * </code>
   */
  function socialSharing( $permalink, $shortlink ) {
    echo <<<EOD
<ul>
  <li>{$this->_myFacebook->shareButton($permalink)}</li>
  <li>{$this->_myTwitter->shareButton($shortlink)}</li>
  <li>{$this->_myGooglePlus->shareButton($permalink)}</li>
</ul>
EOD;
  }
}

$bsp = new Bubs_Social_Plugin();
?>