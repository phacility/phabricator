<?php

abstract class HarbormasterConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorHarbormasterApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('All Harbormaster APIs are new and subject to change.');
  }

  protected function returnArtifactList(array $artifacts) {
    $list = array();

    foreach ($artifacts as $artifact) {
      $list[] = array(
        'phid' => $artifact->getPHID(),
      );
    }

    return $list;
  }

}
