<?php

abstract class AlmanacServiceController extends AlmanacController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('service/');
    $crumbs->addTextCrumb(pht('Services'), $list_uri);

    return $crumbs;
  }

  protected function getPropertyDeleteURI($object) {
    $id = $object->getID();
    return "/almanac/service/delete/{$id}/";
  }

  protected function getPropertyUpdateURI($object) {
    $id = $object->getID();
    return "/almanac/service/property/{$id}/";
  }

}
