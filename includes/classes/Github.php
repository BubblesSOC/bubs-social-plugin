<?php
/**
 * MyGithub Class
 *
 * @package Bubs_Social_Plugin
 * @since 1.0
 */

class MyGithub extends MySocial {
  function __construct() {
    $this->apiUrl = "https://api.github.com/";
    $this->cacheOptionName = 'github_cache';
    $this->initCache();
    $this->hookAjax('bsp-print-repos', 'printPublicRepos');
  }

  protected function checkServiceError( $response_code, $response_body ) {
    if ( $response_code['code'] == 400 || $response_code['code'] == 422 ) {
      return new WP_Error( 'service_error', $response_body->message );
    }
    return $response_body;
  }
  
  function printPublicRepos() {
    $result = $this->_getPublicRepos();
    $this->printStatus($result);
    foreach ( $this->cache['items'] as $repo ) {
      echo '<li><a href="'. $repo['url'] .'">'. $repo['name'] .'</a><p>'. $repo['description'] .'</p></li>' . "\n";
    }
    exit;
  }
  
  private function _getPublicRepos() {
    // Ref: http://developer.github.com/v3/repos/
    return $this->fetchItems( $this->apiUrl . 'users/bubblessoc/repos' );
  }
  
  protected function parseResponse( $response ) {
    $repos = array_reverse($response);
    $items = array();
    for ($i=0; $i<3; $i++) {
      $item = array(
        'url' => $repos[$i]->html_url,
        'git_url' => $repos[$i]->git_url,
        'clone_url' => $repos[$i]->clone_url,
        'created_at' => $repos[$i]->created_at,
        'updated_at' => $repos[$i]->updated_at,
        'name' => $repos[$i]->name,
        'description' => $repos[$i]->description
      );
      array_push($items, $item);
    }
    return $items;
  }
}
?>