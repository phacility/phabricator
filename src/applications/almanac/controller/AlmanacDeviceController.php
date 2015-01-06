<?php

abstract class AlmanacDeviceController extends AlmanacController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('device/');
    $crumbs->addTextCrumb(pht('Devices'), $list_uri);

    return $crumbs;
  }

}
