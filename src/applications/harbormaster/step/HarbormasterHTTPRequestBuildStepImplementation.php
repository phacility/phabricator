<?php

final class HarbormasterHTTPRequestBuildStepImplementation
  extends VariableBuildStepImplementation {

  public function getName() {
    return pht('Make HTTP Request');
  }

  public function getGenericDescription() {
    return pht('Make an HTTP request.');
  }

  public function getDescription() {
    $settings = $this->getSettings();

    $uri = new PhutilURI($settings['uri']);
    $domain = $uri->getDomain();
    return pht('Make an HTTP %s request to %s', $settings['method'], $domain);
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();
    $variables = $build_target->getVariables();

    $uri = $this->mergeVariables(
      'vurisprintf',
      $settings['uri'],
      $variables);

    $log_body = $build->createLog($build_target, $uri, 'http-body');
    $start = $log_body->start();

    $method = 'POST';
    if ($settings['method'] !== '') {
      $method = $settings['method'];
    }

    list($status, $body, $headers) = id(new HTTPSFuture($uri))
      ->setMethod($method)
      ->setTimeout(60)
      ->resolve();

    $log_body->append($body);
    $log_body->finalize($start);

    if ($status->getStatusCode() != 200) {
      $build->setBuildStatus(HarbormasterBuild::STATUS_FAILED);
    }
  }

  public function validateSettings() {
    $settings = $this->getSettings();

    if ($settings['uri'] === null || !is_string($settings['uri'])) {
      return false;
    }

    $methods = array(
      'GET' => true,
      'POST' => true,
      'DELETE' => true,
      'PUT' => true,
    );

    $method = idx($settings, 'method');
    if (strlen($method)) {
      if (empty($methods[$method])) {
        return false;
      }
    }

    return true;
  }

  public function getSettingDefinitions() {
    return array(
      'uri' => array(
        'name' => 'URI',
        'description' => pht('The URI to request.'),
        'type' => BuildStepImplementation::SETTING_TYPE_STRING,
      ),
      'method' => array(
        'name' => 'Method',
        'description' =>
          pht('Request type. Should be GET, POST, PUT, or DELETE.'),
        'type' => BuildStepImplementation::SETTING_TYPE_STRING,
      ),
    );
  }

}
