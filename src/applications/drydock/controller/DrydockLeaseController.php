<?php

abstract class DrydockLeaseController
  extends DrydockController {

  private $resource;

  public function setResource($resource) {
    $this->resource = $resource;
    return $this;
  }

  public function getResource() {
    return $this->resource;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new DrydockLeaseSearchEngine())
      ->setViewer($this->getRequest()->getUser());

    if ($this->getResource()) {
      $engine->setResource($this->getResource());
    }

    $engine->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $resource = $this->getResource();
    if ($resource) {
      $id = $resource->getID();

      $crumbs->addTextCrumb(
        pht('Resources'),
        $this->getApplicationURI('resource/'));

      $crumbs->addTextCrumb(
        $resource->getResourceName(),
        $this->getApplicationURI("resource/{$id}/"));

      $crumbs->addTextCrumb(
        pht('Leases'),
        $this->getApplicationURI("resource/{$id}/leases/"));

    } else {
      $crumbs->addTextCrumb(
        pht('Leases'),
        $this->getApplicationURI('lease/'));
    }
    return $crumbs;
  }

}
