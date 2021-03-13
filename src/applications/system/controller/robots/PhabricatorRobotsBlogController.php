<?php

final class PhabricatorRobotsBlogController
  extends PhabricatorRobotsController {

  protected function newRobotsRules() {
    $out = array();

    // Allow everything on blog domains to be indexed.

    $out[] = 'User-Agent: *';
    $out[] = 'Crawl-delay: 1';

    return $out;
  }

}
