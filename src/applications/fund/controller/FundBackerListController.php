<?php

final class FundBackerListController
  extends FundController {

  private $id;
  private $queryKey;
  private $initiative;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();

    if ($this->id) {
      $this->initiative = id(new FundInitiativeQuery())
        ->setViewer($request->getUser())
        ->withIDs(array($this->id))
        ->executeOne();
      if (!$this->initiative) {
        return new Aphront404Response();
      }
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->queryKey)
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
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $engine = id(new FundBackerSearchEngine())
      ->setViewer($viewer);

    if ($this->initiative) {
      $engine->setInitiative($this->initiative);
    }

    return $engine;
  }

}
