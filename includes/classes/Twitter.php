<?php
/**
 * MyTwitter Class
 *
 * @package Bubs_Social_Plugin
 * @subpackage MyTwitter
 * @since 1.0
 */
class MyTwitter extends MySocial_Oauth {
  function __construct() {
    $this->service = 'Twitter';
    $this->apiUrl = "http://api.twitter.com/1/";
    $this->signatures = array(
      'consumer_key'  => TWITTER_CONSUMER_KEY,
      'shared_secret' => TWITTER_CONSUMER_SECRET,
      'access_token'  => TWITTER_ACCESS_TOKEN,
      'access_secret' => TWITTER_ACCESS_TOKEN_SECRET
    );
    $this->cacheOptionName = 'twitter_cache';
    $this->initSettingsPage = true;
    $this->widgetClassName = 'MyTwitter_Widget';
    $this->initCache( array('user_timeline') );
    $this->hookAjax('bsp-print-tweets', 'printUserTimeline');
    
    // Add @Anywhere
    // Ref: https://dev.twitter.com/docs/anywhere/welcome
    add_action( 'wp_enqueue_scripts', array($this, 'anywhereJS') );
    
    // Tweet Post on Publish
    add_action( 'add_meta_boxes', array($this, 'addMetaBox') );
    add_action( 'transition_post_status', array($this, 'tweetPostOnPublish'), 11, 3 ); // want to perform this action after shortlink creation
    
    // Manually Tweet Post
    add_action( 'wp_ajax_bsp-tweet-post', array($this, 'tweetPostManually') );
  }
  
  protected function checkServiceError( $response ) {
    $response_body = json_decode( $response['body'] );
    if ( isset($response_body->error) ) {
      return new WP_Error( 'service_error', $response_body->error );
    }
    if ( isset($response_body->errors) ) {
      return new WP_Error( 'service_error', $response_body->errors[0]->message );
    }
    return $response_body;
  }
  
  function anywhereJS() {
    wp_enqueue_script( 'twitter_anywhere', 'http://platform.twitter.com/anywhere.js?id='. TWITTER_CONSUMER_KEY .'&v=1' );
  }
  
  function embeddedJS() {
    return <<<EOD
window.twttr = (function (d,s,id) {
  var t, js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return; js=d.createElement(s); js.id=id;
  js.src="//platform.twitter.com/widgets.js"; fjs.parentNode.insertBefore(js, fjs);
  return window.twttr || (t = { _e: [], ready: function(f){ t._e.push(f) } });
}(document, "script", "twitter-wjs"));
EOD;
  }
  
  function shareButton( $shortlink ) {
    return '<a href="https://twitter.com/share" class="twitter-share-button" data-lang="en" data-url="'. $shortlink .'" data-via="BubblesSOC">Tweet</a>';
  }
  
  function addMetaBox() {
    // Action Hook: do_action('add_meta_boxes', $post_type, $post);
    // Ref: wp-admin/edit-form-advanced.php 
    add_meta_box('twitterdiv', 'Post to Twitter', array($this, 'metaBoxContent'), 'post', 'side', 'high', array());
  }
  
  function metaBoxContent( $post, $args ) {
    if ( $parent_id = wp_is_post_revision($post->ID) ) 
      $post = get_post($parent_id);
    
    $tweet_link = get_post_meta($post->ID, 'tweet_link');
    
    if ( empty($tweet_link) ) {
      // Handle custom errors
      $this->_tweetErrorNotice($post);
      $this->_tweetSuccessNotice();
      
      echo '<div id="bsp-tweet-container">';
      wp_nonce_field('bsp-tweet-post', 'bsp-tweet-post-nonce');
      echo '<textarea rows="3" cols="30" maxlength="140" name="bsp_tweet_text" id="bsp-tweetbox">'. $this->_getTweetText($post) .'</textarea><br />';
      echo 'Characters Remaining: <strong id="bsp-char-count">140</strong><br /><br />';
      echo '<input type="checkbox" id="bsp-tweet-this" name="bsp_tweet_this" value="true" /> ';
      echo '<label for="bsp-tweet-this">Tweet this when post is published</label>';
      echo '<div id="bsp-tweet-button-div">';
      echo '<img src="'. admin_url('images/wpspin_light.gif') .'" class="ajax-loading" id="bsp-tweet-this-spinner" alt="" /> ';
      echo '<a href="#" class="button" id="bsp-tweet-this-button" data-postid="'. $post->ID .'">Tweet This Now</a>';
      echo '</div></div>';
    }
    else {
      // Display tweet permalink
      echo '<a href="'. $tweet_link[0] .'" id="bsp-tweet-permalink">Tweet Permalink</a>';
    }
  }
  
  private function _tweetErrorNotice( $post ) {
    // Custom error notice for post metabox
    $tweet_error = get_post_meta($post->ID, 'tweet_error');
    if ( !empty($tweet_error) ) {
      delete_post_meta($post->ID, 'tweet_error');
      $display = ' style="display: block;"';
      $message = $tweet_error[0];
    }
    else {
      $display = '';
      $message = '';
    }
    echo '<div id="bsp-tweet-error" class="error"'. $display .'><p>Your last attempt to tweet this post failed: <strong id="bsp-tweet-error-message">'. $message .'</strong></p></div>';
  }
  
  private function _tweetSuccessNotice() {
    // Custom success notice for post metabox
    echo '<div id="bsp-tweet-success" class="updated"><p>Tweeted! <a href="#" id="bsp-tweet-success-link">View Tweet</a></p></div>';
  }
  
  private function _getTweetText( $post ) {
    // Default tweet text for post metabox
    $tweet_text = '';
    if ( $post->post_status == 'publish' ) {
      $tweet_text .= $post->post_title .': ';
    }
    $shortlink = get_post_meta($post->ID, 'bitly_link');
    if ( empty($shortlink) ) {
      $tweet_text .= '[shortlink]';
    }
    else {
      $tweet_text .= $shortlink[0];
    }
    return $tweet_text;
  }
  
  function tweetPostOnPublish( $new_status, $old_status, $post ) {
    if ( !current_user_can('edit_posts') || $new_status != 'publish' || empty($_POST['bsp_tweet_text']) || !isset($_POST['bsp_tweet_this']) || $_POST['bsp_tweet_this'] != true )
      return;
    
    // Function dies if not referred from admin page
    check_admin_referer('bsp-tweet-post', 'bsp-tweet-post-nonce');
    
    if ( $parent_id = wp_is_post_revision($post->ID) ) 
      $post = get_post($parent_id);
    
    // Confirm this post hasn't already been tweeted
    $tweet_link = get_post_meta($post->ID, 'tweet_link');
    if ( $post->post_type != 'post' || !empty($tweet_link) )
      return;
      
    $tweet_text = $_POST['bsp_tweet_text'];
    
    // Replace [shortlink] with Bit.ly shortlink if possible
    if ( stripos($tweet_text, '[shortlink]') !== false ) {
      $shortlink = get_post_meta($post->ID, 'bitly_link');
      if ( empty($shortlink) ) {
        update_post_meta( $post->ID, 'tweet_error', 'No Bit.ly shortlink associated with this post.' );
        return;
      }
      $tweet_text = str_ireplace('[shortlink]', $shortlink[0], $tweet_text);
    }
    
    // Post to Twitter
    $response = $this->_updateStatus($tweet_text);
    if ( is_wp_error($response) ) {
      update_post_meta( $post->ID, 'tweet_error', $response->get_error_message() );
    }
    else {
      update_post_meta( $post->ID, 'tweet_link', "http://twitter.com/bubblessoc/status/{$response->id_str}" );
    }
  }
  
  function tweetPostManually() {
    header("Content-Type: application/json");
    $response = array(
      'status' => 'error',
      'data'   => null
    );
    if ( !check_ajax_referer('bsp-tweet-post', 'bsp_nonce', false) || !current_user_can('edit_posts') ) {
      $response['data'] = 'You do not have sufficient permissions to access this page.';
      echo json_encode($response);
      exit;  
    }
    if ( !isset($_POST['post_id']) || !is_numeric($_POST['post_id']) || !isset($_POST['tweet_text']) || empty($_POST['tweet_text']) ) {
      $response['data'] = 'Required fields are missing.';
      echo json_encode($response);
      exit;
    }
    
    // Retrieve post for update
    $post = get_post($_POST['post_id']);
    if ( is_null($post) ) {
      $response['data'] = "Post {$_POST['post_id']} not found.";
      echo json_encode($response);
      exit;
    }
    
    if ( $parent_id = wp_is_post_revision($post->ID) ) 
      $post = get_post($parent_id);
    
    // If post has already been tweeted, return the permalink
    $tweet_link = get_post_meta($post->ID, 'tweet_link');
    if ( !empty($tweet_link) ) {
      $response['status'] = 'success';
      $response['data'] = $tweet_link[0];
      echo json_encode($response);
      exit;
    }
    
    $tweet_text = $_POST['tweet_text'];
    
    // Replace [shortlink] with Bit.ly shortlink if possible
    if ( stripos($tweet_text, '[shortlink]') !== false ) {
      $shortlink = get_post_meta($post->ID, 'bitly_link');
      if ( empty($shortlink) ) {
        $response['data'] = "No Bit.ly shortlink associated with this post.";
        echo json_encode($response);
        exit;
      }
      $tweet_text = str_ireplace('[shortlink]', $shortlink[0], $tweet_text);
    }
    
    // Post to Twitter
    $update_response = $this->_updateStatus($tweet_text);
    if ( is_wp_error($update_response) ) {
      $response['data'] = $update_response->get_error_message();
      echo json_encode($response);
      exit;
    }
    
    $tweet_link = "http://twitter.com/bubblessoc/status/{$update_response->id_str}";
    update_post_meta( $post->ID, 'tweet_link', $tweet_link );
    $response['status'] = 'success';
    $response['data'] = $tweet_link;
    echo json_encode($response);
    exit;
  }
  
  private function _updateStatus( $tweet_text ) {
    // Update Twitter Status
    // Ref: https://dev.twitter.com/docs/api/1/post/statuses/update
    $params = array( 'status' => stripslashes($tweet_text) );
    return $this->requestResource( $this->getSignedURL("POST", $this->apiUrl . 'statuses/update.json', $params), "POST" );
  }
  
  function printUserTimeline() {
    $result = $this->_getUserTimeline();
    $this->printStatus($result);
    foreach ( $this->cache['user_timeline']['items'] as $tweet ) {
      $tweet = $this->_convertUrls($tweet);
      $tweet = $this->_convertHashtags($tweet);
      $tweet = $this->_linkifyUsers($tweet);
      $time_diff = human_time_diff( strtotime($tweet['created_at']) );
      echo "<li>\n<p>" . rk_convert_heart( convert_smilies( wptexturize($tweet['text']) ) ) . "</p>\n";
      echo "<ul>\n";
      echo '<li><a href="http://twitter.com/bubblessoc/status/'. $tweet['id'] .'">'. $time_diff .' ago</a></li>' . "\n";
      echo '<li><a href="https://twitter.com/intent/tweet?in_reply_to='. $tweet['id'] .'">Reply</a></li>' . "\n";
      echo '<li><a href="https://twitter.com/intent/retweet?tweet_id='. $tweet['id'] .'">Retweet</a></li>' . "\n";
      echo '<li><a href="https://twitter.com/intent/favorite?tweet_id='. $tweet['id'] .'">Favorite</a></li>' . "\n";
      echo "</ul>\n</li>\n";
    }
    exit;
  }
  
  private function _convertUrls( $tweet ) {
    foreach ( $tweet['entities']['urls'] as $url ) {
      $display_url = '<a href="'. $url['expanded_url'] .'">'. $url['display_url'] .'</a>';
      $tweet['text'] = str_replace( $url['url'], $display_url, $tweet['text'] );
    }
    return $tweet;
  }
  
  private function _convertHashtags( $tweet ) {
    foreach ( $tweet['entities']['hashtags'] as $tag ) {
      $display = '<a href="http://twitter.com/search?q='. urlencode("#{$tag['text']}") .'">#'. $tag['text'] .'</a>';
      $tweet['text'] = str_replace( "#{$tag['text']}", $display, $tweet['text'] );
    }
    return $tweet;
  }
  
  private function _linkifyUsers( $tweet ) {
    foreach ( $tweet['entities']['user_mentions'] as $mention ) {
      $href = "http://twitter.com/{$mention['screen_name']}";
      if ( isset($tweet['retweeted_status_id']) ) {
        $href .= "/status/{$tweet['retweeted_status_id']}";
      }
      elseif ( $mention['screen_name'] == $tweet['in_reply_to_screen_name'] && !is_null($tweet['in_reply_to_status_id']) ) {
        $href .= "/status/{$tweet['in_reply_to_status_id']}";
      }
      $display = '<a href="'. $href .'" class="tweep">@'. $mention['screen_name'] .'</a>';
      $tweet['text'] = str_ireplace( "@{$mention['screen_name']}", $display, $tweet['text'] );
    }
    return $tweet;
  }
  
  private function _getUserTimeline() {
    // Ref: https://dev.twitter.com/docs/api/1/get/statuses/user_timeline
    $params = array(
      'count' => 5,
      'trim_user' => false,
      'include_rts' => true,
      'include_entities' => true
    );
    return $this->fetchItems( 'user_timeline', 'parseUserTimelineResponse', $this->getSignedURL("GET", $this->apiUrl . 'statuses/user_timeline.json', $params) );
  }
  
  function parseUserTimelineResponse( $response ) {
    $items = array();
    foreach ( $response as $tweet ) {
      $item = array(
        'id' => $tweet->id_str,
        'in_reply_to_status_id' => $tweet->in_reply_to_status_id_str,
        'in_reply_to_screen_name' => $tweet->in_reply_to_screen_name,
        'created_at' => $tweet->created_at,
        'text' => $this->convertChars( $tweet->text ),
        'entities' => $this->_parseTweetEntities( $tweet->entities )
      );
      if ( isset($tweet->retweeted_status) ) {
        $item['text'] = "RT @{$tweet->retweeted_status->user->screen_name}: " . $this->convertChars( $tweet->retweeted_status->text );
        $item['retweeted_status_id'] = $tweet->retweeted_status->id_str;
        $item['entities'] = $this->_parseTweetEntities( $tweet->retweeted_status->entities, $item['entities'] );
      }
      array_push($items, $item);
    }
    return $items;
  }
  
  private function _parseTweetEntities( $entities, $ent_arr = null ) {
    // Entities
    // Ref: https://dev.twitter.com/docs/tweet-entities
    if ( is_null($ent_arr) ) {
      $ent_arr = array(
        'urls' => array(),
        'user_mentions' => array(),
        'hashtags' => array()
      );
    }
    foreach ( $entities->urls as $url ) {
      unset($url->indices);
      array_push( $ent_arr['urls'], (array) $url ); 
    }
    foreach ( $entities->user_mentions as $mention ) {
      array_push( $ent_arr['user_mentions'], array('screen_name' => $mention->screen_name) );
    }
    foreach ( $entities->hashtags as $tag ) {
      unset($tag->indices);
      array_push( $ent_arr['hashtags'], (array) $tag );
    }
    return $ent_arr;
  }
}

/**
 * MyTwitter_Widget Class
 *
 * @package Bubs_Social_Plugin
 * @subpackage MyTwitter
 * @since 1.0
 */
class MyTwitter_Widget extends MySocial_Widget {
  function __construct() {
    parent::registerWidget( 'twitter', 'Twitter', 'Your most recent tweets' );
  }
}
?>