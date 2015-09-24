<?php

abstract class DrydockResourceController
  extends DrydockController {

  private $blueprint;

  public function setBlueprint($blueprint) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->blueprint;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new DrydockResourceSearchEngine())
      ->setViewer($this->getViewer());

    if ($this->getBlueprint()) {
      $engine->setBlueprint($this->getBlueprint());
    }

    $engine->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $blueprint = $this->getBlueprint();
    if ($blueprint) {
      $id = $blueprint->getID();
      $crumbs->addTextCrumb(
        pht('Blueprints'),
        $this->getApplicationURI('blueprint/'));

      $crumbs->addTextCrumb(
        $blueprint->getBlueprintName(),
        $this->getApplicationURI("blueprint/{$id}/"));

      $crumbs->addTextCrumb(
        pht('Resources'),
        $this->getApplicationURI("blueprint/{$id}/resources/"));
    } else {
      $crumbs->addTextCrumb(
        pht('Resources'),
        $this->getApplicationURI('resource/'));
    }
    return $crumbs;
  }

}
