<?php

final class JenkinsAPIRequest
{

  private $jobName;
  private $buildNumber;
  private $suffix;
  private $params = array();
  private $expects = 'json';

  private $response;

  public static function create() {
    return new self();
  }

  public function __construct() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/httpful/bootstrap.php';
  }

  public function addJob($job_name) {
    $this->jobName = $job_name;

    return $this;
  }

  public function addBuild($build_number) {
    $this->buildNumber = $build_number;

    return $this;
  }

  public function setSuffix($suffix) {
    $this->suffix = $suffix;

    return $this;
  }

  public function setParams(array $params) {
    $this->params = $params;

    return $this;
  }

  /**
   * @phutil-external-symbol class \Httpful\Request
   */
  public function query() {
    $url = $this->buildUrl();

    $cache_file = sys_get_temp_dir().'/ph-'.crc32($url).'.cache';
    if (!isset($this->response) && file_exists($cache_file)) {
      $this->response = unserialize(file_get_contents($cache_file));
    }

    if (!isset($this->response)) {
      $user_id = PhabricatorEnv::getEnvConfig('jenkins.user-id');
      $api_token = PhabricatorEnv::getEnvConfig('jenkins.api-token');

      /** @var \Httpful\Response $response */
      $response = \Httpful\Request::get($url, $this->expects)
        ->authenticateWith($user_id, $api_token)
        ->send();

      if ($response->hasErrors()) {
        throw new Exception('Jenkins request failed with '.$response->code.' HTTP code');
      }

      $this->response = $response->body;
      file_put_contents($cache_file, serialize($this->response));
    }

    return $this->response;
  }

  private function buildUrl() {
    $url = 'http://'.PhabricatorEnv::getEnvConfig('jenkins.host');

    if ($this->jobName) {
      $url .= '/job/'.$this->jobName;
    }

    if ($this->buildNumber) {
      if (!$this->jobName) {
        throw new Exception('Job name is required, when specifying the build number');
      }

      $url .= '/' . $this->buildNumber;
    }

    if ($this->suffix) {
      $url .= '/' . $this->suffix;
    }

    $url .= '/api/'.$this->expects;

    if ($this->params) {
      $url .= '?'.http_build_query($this->params);
    }

    return $url;
  }

}
