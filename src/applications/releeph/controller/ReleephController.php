<?php

abstract class ReleephController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName(pht('Releeph'));
    $page->setBaseURI('/releeph/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xD3\x82");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('project/create/', pht('Create Product'));
    }

    id(new ReleephProductSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }


  protected function getProductViewURI(ReleephProject $product) {
    return $this->getApplicationURI('project/'.$product->getID().'/');
  }

  protected function getBranchViewURI(ReleephBranch $branch) {
    return $this->getApplicationURI('branch/'.$branch->getID().'/');
  }

}
