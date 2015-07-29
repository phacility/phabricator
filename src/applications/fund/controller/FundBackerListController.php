<?php

final class FundBackerListController
  extends FundController {

  private $initiative;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $querykey = $request->getURIData('queryKey');

    if ($id) {
      $this->initiative = id(new FundInitiativeQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->executeOne();
      if (!$this->initiative) {
        return new Aphront404Response();
      }
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($querykey)
      ->setSearchEngine($this->getEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $this->getEngine()->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      pht('Backers'),
      $this->getApplicationURI('backers/'));

    if ($this->initiative) {
      $crumbs->addTextCrumb(
        $this->initiative->getMonogram(),
        '/'.$this->initiative->getMonogram());
    }

    return $crumbs;
  }

  private function getEngine() {
    $viewer = $this->getViewer();

    $engine = id(new FundBackerSearchEngine())
      ->setViewer($viewer);

    if ($this->initiative) {
      $engine->setInitiative($this->initiative);
    }

    return $engine;
  }

}
