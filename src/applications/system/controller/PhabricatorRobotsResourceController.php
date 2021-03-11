<?php

final class PhabricatorRobotsResourceController
  extends PhabricatorRobotsController {

  protected function newRobotsRules() {
    $out = array();

    // See T13636. Prevent indexing of any content on resource domains.

    $out[] = 'User-Agent: *';
    $out[] = 'Disallow: /';

    return $out;
  }

}
