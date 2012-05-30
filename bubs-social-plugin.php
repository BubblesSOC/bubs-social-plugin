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
require_once('includes/classes/Dribbble.php');
require_once('includes/classes/Facebook.php');
require_once('includes/classes/Flickr.php');
require_once('includes/classes/Github.php');
require_once('includes/classes/GooglePlus.php');
// require_once('includes/classes/Lastfm.php');
// require_once('includes/classes/Pinterest.php');
require_once('includes/classes/Tumblr.php');
require_once('includes/classes/Twitter.php');

define('BSP_PLUGIN_SLUG', "bubs-social-plugin");
define('BSP_DIR_PATH', plugin_dir_path(__FILE__));
define('BSP_DIR_URL', plugin_dir_url(__FILE__));

/**
 * BSP (Bubs' Social Plugin) Base Class
 *
 * Sets up the plugin's general WordPress functionality and instantiates the social classes.
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */
class Bubs_Social_Plugin {
  private $_myDribbble;
  private $_myFacebook;
  private $_myFlickr;
  private $_myGithub;
  private $_myGooglePlus;
  private $_myLastfm;
  private $_myPinterest;
  private $_myTumblr;
  private $_myTwitter;
  
  public static $optionGroupName = 'bsp_reset_cache';
  
  function __construct() {
    $this->_myDribbble = new MyDribbble();
    $this->_myFacebook = new MyFacebook();
    $this->_myFlickr = new MyFlickr();
    $this->_myGithub = new MyGithub();
    $this->_myGooglePlus = new MyGooglePlus();
    // $this->_myLastfm = new MyLastFM();
    // $this->_myPinterest = new MyPinterest();
    $this->_myTumblr = new MyTumblr();
    $this->_myTwitter = new MyTwitter();
    
    // Admin & Social JS
    add_action('wp_enqueue_scripts', array($this, 'socialJS'));
    add_action('wp_footer', array($this, 'embeddedJS'));
    add_action('admin_enqueue_scripts', array($this, 'adminIncludes'));
    
    // Admin Settings/Options Page
    add_action('admin_init', array($this, 'initSettingsPage'));
    add_action('admin_menu', array($this, 'addSettingsPage'));
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'linkSettingsPage'));

    // Comment via Social
    add_filter('wp_get_current_commenter', array($this, 'social_getCurrentCommenter'));
    add_action('comment_form_top', array($this, 'commentForm_socialFields'), 9);
  	add_action('comment_form_before_fields', array($this, 'commentForm_identities'), 11);
  	add_filter('get_avatar', array($this, 'social_getAvatar'), 10, 5);
  	add_filter('get_comment_author_link', array($this, 'social_getCommentAuthorLink'));
  	add_action('comment_post', array($this, 'commentViaSocial'), 10, 2);
  	
  	// Social Sharing
  	add_action('bsp_share_buttons', array($this, 'socialSharing'), 10, 2);
  	add_filter('bsp_facebook_metatag', array($this, 'addFacebookMetaTag'));
  	
  	// Hook Ajax for Likes
  	add_action('wp_ajax_nopriv_bsp-print-likes', array($this, 'printLikes'));
    add_action('wp_ajax_bsp-print-likes', array($this, 'printLikes'));
  }
  
  function socialJS() {
    wp_enqueue_script('bsp_social_js', plugins_url('/includes/js/bsp-social.js', __FILE__), array('jquery', 'rk_wpcomments_js'), false, true);
  }
  
  /**
   * Loads the services' JS for client-side functionality
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
  
  /**
   * Only include admin CSS and JS on admin post pages
   *
   * @global string $pagenow
   */
  function adminIncludes() {
    global $pagenow;
    if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) {
      wp_enqueue_script('bsp_admin_js', plugins_url('/includes/js/bsp-admin.js', __FILE__), array('jquery'));
      wp_enqueue_style('bsp_admin_css', plugins_url('/includes/bsp-admin.css', __FILE__));
    }
  }
  
  /**
   * Admin Settings/Options Page
   */
  function initSettingsPage() {
    $this->_myDribbble->initSettingsPage();
    $this->_myFlickr->initSettingsPage();
    $this->_myGithub->initSettingsPage();
    $this->_myTumblr->initSettingsPage();
    $this->_myTwitter->initSettingsPage();
  }
  
  function addSettingsPage() {
    add_options_page("Bubs' Social Plugin", "Bubs' Social Plugin", 'manage_options', BSP_PLUGIN_SLUG, array($this, 'settingsPage'));
  }
  
  function settingsPage() {
    if ( !current_user_can('manage_options') )  {
  		wp_die( __('You do not have sufficient permissions to access this page.') );
  	}
?>
<div class="wrap">
  <div id="icon-options-general" class="icon32"><br></div>
	<h2>Bubs' Social Plugin Settings</h2>
	<form action="options.php" method="post">
<?php
settings_fields( Bubs_Social_Plugin::$optionGroupName );
do_settings_sections(BSP_PLUGIN_SLUG);
submit_button('Reset Cache');
?>
  </form>
</div>
<?php
  }
  
  /**
   * Add link to BSP Settings page under "Installed Plugins"
   */
  function linkSettingsPage( $actions ) {
    $actions[] = '<a href="options-general.php?page='. BSP_PLUGIN_SLUG .'">Settings</a>';
    return $actions;
  }
  
  /**
   * Adds social info (service, name, profile image, profile link) as comment meta
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
   * Replaces Gravatar with profile photo
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
   * Adds 'via service_name' after commenter's name
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
   * Adds the social fields to the comment form
   */
  function commentForm_socialFields() {
    echo <<<EOD
<input type="hidden" name="social[name]" id="social-name-field" value="" disabled />
<input type="hidden" name="social[profile_link]" id="social-link-field" value="" disabled />
<input type="hidden" name="social[profile_image]" id="social-image-field" value="" disabled />   
EOD;
  }

  /**
   * Adds the 'comment via' radio buttons to the comment form
   */
  function commentForm_identities() {
    $default_img = BSP_DIR_URL . "includes/images/mystery-man.png";
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
  <li>{$this->_myFacebook->likeButton($permalink)}</li>
  <li>{$this->_myFacebook->shareButton()}</li>
  <li>{$this->_myTwitter->shareButton($shortlink)}</li>
  <li>{$this->_myGooglePlus->shareButton($permalink)}</li>
</ul>
EOD;
  }
  
  /**
   * Retrieves meta tag containing Facebook app id for Open Graph Protocol
   *
   * Filter Hook:
   * <code>
   * return apply_filters( 'bsp_facebook_metatag', $metadata . "\n" );
   * </code>
   *
   * @see Open_Graph_Protocol::getMetadata()
   */
  function addFacebookMetaTag( $metadata ) {
    return $metadata . $this->_myFacebook->metaTag();
  }
   
  /**
   * Aggregates & displays images I've favorited across social sites
   *
   * @uses MyTumblr::getLikesCache()
   * @uses MyDribbble::getLikesCache()
   * @uses MyFlickr::getLikesCache()
   * @uses Bubs_Social_Plugin::compareTimestamps()
   */
  function printLikes() {
    $likes = array_merge( $this->_myTumblr->getLikesCache(), $this->_myDribbble->getLikesCache(), $this->_myFlickr->getLikesCache() );
    usort( $likes, array('Bubs_Social_Plugin', 'compareTimestamps') );
    $likes = array_slice( $likes, 0, 5 );
    foreach ( $likes as $like ) {
      if ( $like['service'] == 'dribbble' ) {
        $href = $like['url'];
        if ( is_null($like['image']['cache_url']) ) {
          $src = $like['image']['teaser_url'];
        }
        else {
          $src = $like['image']['cache_url'];
        }
        $alt = "{$like['title']} by {$like['player']['name']} on Dribbble";
      }
      elseif ( $like['service'] == 'tumblr' ) {
        $href = $like['post_url'];
        $src = $like['photos'][0]['thumbnail']['url'];
        $alt = "{$like['photos'][0]['caption']} by {$like['blog_name']} on Tumblr";
      }
      elseif ( $like['service'] == 'flickr' ) {
        $href = $like['link'];
        $src = $like['url_sq'];
        $alt = "{$like['title']} by {$like['owner_name']} on Flickr";
      }
      echo '<li><a href="'. $href .'" title="'. $alt .'"><img src="'. $src .'" alt="'. $alt .'" /></a></li>' . "\n";
    }
    exit;
  }
  
  /**
   * Callback function to sort array by timestamps
   */
  static function compareTimestamps( $a, $b ) {
    if ( $a['timestamp'] == $b['timestamp'] ) {
      return 0;
    }
    // want descending order
    return ( $a['timestamp'] > $b['timestamp'] ) ? -1 : 1;
  }
}

$bsp = new Bubs_Social_Plugin();
?>