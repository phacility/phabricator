<?php

final class PhabricatorRobotsPlatformController
  extends PhabricatorRobotsController {

  protected function newRobotsRules() {
    $out = array();

    // Prevent indexing of '/diffusion/', since the content is not generally
    // useful to index, web spiders get stuck scraping the history of every
    // file, and much of the content is Ajaxed in anyway so spiders won't even
    // see it. These pages are also relatively expensive to generate.

    // Note that this still allows commits (at '/rPxxxxx') to be indexed.
    // They're probably not hugely useful, but suffer fewer of the problems
    // Diffusion suffers and are hard to omit with 'robots.txt'.

    $out[] = 'User-Agent: *';
    $out[] = 'Disallow: /diffusion/';
    $out[] = 'Disallow: /source/';

    return $out;
  }

}
