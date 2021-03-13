<?php

abstract class PhabricatorRobotsController extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  final public function processRequest() {
    $out = $this->newRobotsRules();

    $content = implode("\n", $out)."\n";

    return id(new AphrontPlainTextResponse())
      ->setContent($content)
      ->setCacheDurationInSeconds(phutil_units('2 hours in seconds'))
      ->setCanCDN(true);
  }

  abstract protected function newRobotsRules();

}
