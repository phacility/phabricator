<?php

abstract class DrydockLogController
  extends DrydockController {

  private $blueprint;
  private $resource;
  private $lease;

  public function setBlueprint(DrydockBlueprint $blueprint) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->blueprint;
  }

  public function setResource(DrydockResource $resource) {
    $this->resource = $resource;
    return $this;
  }

  public function getResource() {
    return $this->resource;
  }

  public function setLease(DrydockLease $lease) {
    $this->lease = $lease;
    return $this;
  }

  public function getLease() {
    return $this->lease;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new DrydockLogSearchEngine())
      ->setViewer($this->getRequest()->getUser());

    $blueprint = $this->getBlueprint();
    if ($blueprint) {
      $engine->setBlueprint($blueprint);
    }

    $resource = $this->getResource();
    if ($resource) {
      $engine->setResource($resource);
    }

    $lease = $this->getLease();
    if ($lease) {
      $engine->setLease($lease);
    }

    $engine->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $blueprint = $this->getBlueprint();
    $resource = $this->getResource();
    $lease = $this->getLease();
    if ($blueprint) {
      $id = $blueprint->getID();

      $crumbs->addTextCrumb(
        pht('Blueprints'),
        $this->getApplicationURI('blueprint/'));

      $crumbs->addTextCrumb(
        $blueprint->getBlueprintName(),
        $this->getApplicationURI("blueprint/{$id}/"));

      $crumbs->addTextCrumb(
        pht('Logs'),
        $this->getApplicationURI("blueprint/{$id}/logs/"));
    } else if ($resource) {
      $id = $resource->getID();

      $crumbs->addTextCrumb(
        pht('Resources'),
        $this->getApplicationURI('resource/'));

      $crumbs->addTextCrumb(
        $resource->getResourceName(),
        $this->getApplicationURI("resource/{$id}/"));

      $crumbs->addTextCrumb(
        pht('Logs'),
        $this->getApplicationURI("resource/{$id}/logs/"));
    } else if ($lease) {
      $id = $lease->getID();

      $crumbs->addTextCrumb(
        pht('Leases'),
        $this->getApplicationURI('lease/'));

      $crumbs->addTextCrumb(
        $lease->getLeaseName(),
        $this->getApplicationURI("lease/{$id}/"));

      $crumbs->addTextCrumb(
        pht('Logs'),
        $this->getApplicationURI("lease/{$id}/logs/"));
    }

    return $crumbs;
  }

}
