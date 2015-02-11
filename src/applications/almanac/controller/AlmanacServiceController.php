<?php

abstract class AlmanacServiceController extends AlmanacController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('service/');
    $crumbs->addTextCrumb(pht('Services'), $list_uri);

    return $crumbs;
  }

}
