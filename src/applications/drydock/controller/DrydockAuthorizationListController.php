<?php

final class DrydockAuthorizationListController
  extends DrydockController {

  private $blueprint;

  public function setBlueprint(DrydockBlueprint $blueprint) {
    $this->blueprint = $blueprint;
    return $this;
  }

  public function getBlueprint() {
    return $this->blueprint;
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = new DrydockAuthorizationSearchEngine();

    $id = $request->getURIData('id');

    $blueprint = id(new DrydockBlueprintQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$blueprint) {
      return new Aphront404Response();
    }

    $this->setBlueprint($blueprint);
    $engine->setBlueprint($blueprint);

    $querykey = $request->getURIData('queryKey');

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $engine = id(new DrydockAuthorizationSearchEngine())
      ->setViewer($this->getViewer());

    $engine->setBlueprint($this->getBlueprint());
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
        pht('Authorizations'),
        $this->getApplicationURI("blueprint/{$id}/authorizations/"));
    }

    return $crumbs;
  }

}
