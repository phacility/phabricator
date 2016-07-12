<?php

abstract class AlmanacNetworkController extends AlmanacController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('network/');
    $crumbs->addTextCrumb(pht('Networks'), $list_uri);

    return $crumbs;
  }

}
