<?php

final class DiffusionRepositoryManagePanelsController
  extends DiffusionRepositoryManageController {

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

    foreach ($panels as $key => $panel) {
      $panel
        ->setViewer($viewer)
        ->setRepository($repository)
        ->setController($this);

      if (!$panel->shouldEnableForRepository($repository)) {
        unset($panels[$key]);
        continue;
      }
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
    $crumbs->addTextCrumb($panel->getManagementPanelLabel());
    $crumbs->setBorder(true);

    $header_text = pht(
      '%s: %s',
      $repository->getDisplayName(),
      $panel->getManagementPanelLabel());

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text)
      ->setHeaderIcon('fa-pencil');
    if ($repository->isTracked()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    }

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('View Repository'))
        ->setHref($repository->getURI())
        ->setIcon('fa-code'));

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setMainColumn($content);

    $curtain = $panel->buildManagementPanelCurtain();
    if ($curtain) {
      $view->setCurtain($curtain);
    }

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
      $key = $panel->getManagementPanelKey();
      $label = $panel->getManagementPanelLabel();
      $icon = $panel->getManagementPanelIcon();
      $href = $panel->getPanelNavigationURI();

      $item = id(new PHUIListItemView())
        ->setKey($key)
        ->setName($label)
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref($href)
        ->setIcon($icon);

      $nav->addMenuItem($item);
    }

    $nav->selectFilter($selected);

    return $nav;
  }

  public function newTimeline(PhabricatorRepository $repository) {
    $timeline = $this->buildTransactionTimeline(
      $repository,
      new PhabricatorRepositoryTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $timeline;
  }


}
