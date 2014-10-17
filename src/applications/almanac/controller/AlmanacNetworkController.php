<?php

abstract class AlmanacNetworkController extends AlmanacController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('network/');
    $crumbs->addTextCrumb(pht('Networks'), $list_uri);

    return $crumbs;
  }

}
