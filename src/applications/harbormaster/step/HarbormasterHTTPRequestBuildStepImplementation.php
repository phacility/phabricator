<?php

final class HarbormasterHTTPRequestBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Make HTTP Request');
  }

  public function getGenericDescription() {
    return pht('Make an HTTP request.');
  }

  public function getDescription() {
    $domain = null;
    $uri = $this->getSetting('uri');
    if ($uri) {
      $domain = id(new PhutilURI($uri))->getDomain();
    }

    return pht(
      'Make an HTTP %s request to %s.',
      $this->formatSettingForDescription('method', 'POST'),
      $this->formatValueForDescription($domain));
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

    $method = nonempty(idx($settings, 'method'), 'POST');

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

  public function getFieldSpecifications() {
    return array(
      'uri' => array(
        'name' => pht('URI'),
        'type' => 'text',
        'required' => true,
      ),
      'method' => array(
        'name' => pht('HTTP Method'),
        'type' => 'select',
        'options' => array_fuse(array('POST', 'GET', 'PUT', 'DELETE')),
      ),
    );
  }

}
