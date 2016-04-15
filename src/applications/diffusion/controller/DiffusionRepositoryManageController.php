<?php

final class DiffusionRepositoryManageController
  extends DiffusionController {

  private $navigation;

  public function buildApplicationMenu() {
    // TODO: This is messy for now; the mobile menu should be set automatically
    // when the body content is a two-column view with navigation.
    if ($this->navigation) {
      return $this->navigation->getMenu();
    }
    return parent::buildApplicationMenu();
  }


  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $panels = DiffusionRepositoryManagementPanel::getAllPanels();

    foreach ($panels as $panel) {
      $panel
        ->setViewer($viewer)
        ->setRepository($repository);
    }

    $selected = $request->getURIData('panel');
    if (!strlen($selected)) {
      $selected = head_key($panels);
    }

    if (empty($panels[$selected])) {
      return new Aphront404Response();
    }

    $nav = $this->renderSideNav($repository, $panels, $selected);
    $this->navigation = $nav;

    $panel = $panels[$selected];

    $content = $panel->buildManagementPanelContent();

    $title = array(
      $panel->getManagementPanelLabel(),
      $repository->getDisplayName(),
    );

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $repository->getDisplayName(),
      $repository->getURI());
    $crumbs->addTextCrumb(
      pht('Manage'),
      $repository->getPathURI('manage/'));
    $crumbs->addTextCrumb($panel->getManagementPanelLabel());

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn($content);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function renderSideNav(
    PhabricatorRepository $repository,
    array $panels,
    $selected) {

    $base_uri = $repository->getPathURI('manage/');
    $base_uri = new PhutilURI($base_uri);

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI($base_uri);

    foreach ($panels as $panel) {
      $nav->addFilter(
        $panel->getManagementPanelKey(),
        $panel->getManagementPanelLabel());
    }

    $nav->selectFilter($selected);

    return $nav;
  }


}
