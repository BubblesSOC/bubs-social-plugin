<?php
/**
 * MyGithub Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */

class MyGithub extends MySocial {
  function __construct() {
    $this->service = 'Github';
    $this->apiUrl = "https://api.github.com/";
    $this->cacheOptionName = 'github_cache';
    $this->initCache( array('public_repos') );
    $this->hookAjax('bsp-print-repos', 'printPublicRepos');
  }

  protected function checkServiceError( $response ) {
    $response_body = json_decode( $response['body'] );
    if ( $response['response']['code'] == 400 || $response['response']['code'] == 422 ) {
      return new WP_Error( 'service_error', $response_body->message );
    }
    return $response_body;
  }
  
  function printPublicRepos() {
    $result = $this->_getPublicRepos();
    $this->printStatus($result);
    foreach ( $this->cache['public_repos']['items'] as $repo ) {
      echo '<li><a href="'. $repo['url'] .'">'. $repo['name'] .'</a><p>'. $repo['description'] .'</p></li>' . "\n";
    }
    exit;
  }
  
  private function _getPublicRepos() {
    // Ref: http://developer.github.com/v3/repos/
    return $this->fetchItems( 'public_repos', 'parsePublicReposResponse', $this->apiUrl . 'users/bubblessoc/repos', 60*60, 'get', array('sslverify' => false) );
  }
  
  function parsePublicReposResponse( $response ) {
    $items = array();
    foreach ( $response as $repo ) {
      $item = array(
        'url' => $repo->html_url,
        'git_url' => $repo->git_url,
        'clone_url' => $repo->clone_url,
        'created_at' => $repo->created_at,
        'updated_at' => $repo->updated_at,
        'timestamp' => strtotime( $repo->updated_at ),
        'name' => $repo->name,
        'description' => $repo->description
      );
      array_push($items, $item);
    }
    usort( $items, array('Bubs_Social_Plugin', 'compareTimestamps') );
    return array_slice( $items, 0, 5 );
  }
}
?>