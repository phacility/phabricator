<?php

abstract class AlmanacServiceController extends AlmanacController {

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $list_uri = $this->getApplicationURI('service/');
    $crumbs->addTextCrumb(pht('Services'), $list_uri);

    return $crumbs;
  }

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new AlmanacServiceSearchEngine());
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
