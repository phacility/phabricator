<?php

final class PhabricatorAWSSESFuture extends PhutilAWSFuture {

  private $parameters;

  public function getServiceName() {
    return 'ses';
  }

  protected function didReceiveResult($result) {
    list($status, $body, $headers) = $result;

    if (!$status->isError()) {
      return $body;
    }

    return parent::didReceiveResult($result);
  }

}
