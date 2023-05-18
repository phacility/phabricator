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
    if ($selected === null || !strlen($selected)) {
      $selected = head_key($panels);
    }

    if (empty($panels[$selected])) {
      return new Aphront404Response();
    }

    $nav = $this->renderSideNav($repository, $panels, $selected);
    $this->navigation = $nav;

    $panel = $panels[$selected];

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($panel->getManagementPanelLabel());
    $crumbs->setBorder(true);

    $content = $panel->buildManagementPanelContent();

    $title = array(
      $panel->getManagementPanelLabel(),
      $repository->getDisplayName(),
    );

    $header = $this->buildHeaderView($repository->getDisplayName());

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn($content);

    $curtain = $panel->buildManagementPanelCurtain();
    if ($curtain) {
      $view->setCurtain($curtain);
    }

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
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

    $groups = DiffusionRepositoryManagementPanelGroup::getAllPanelGroups();
    $panel_groups = mgroup($panels, 'getManagementPanelGroupKey');
    $other_key = DiffusionRepositoryManagementOtherPanelGroup::PANELGROUPKEY;

    foreach ($groups as $group_key => $group) {
      // If this is the "Other" group, include everything else that isn't in
      // some actual group.
      if ($group_key === $other_key) {
        $group_panels = array_mergev($panel_groups);
        $panel_groups = array();
      } else {
        $group_panels = idx($panel_groups, $group_key);
        unset($panel_groups[$group_key]);
      }

      if (!$group_panels) {
        continue;
      }

      $label = $group->getManagementPanelGroupLabel();
      if ($label) {
        $nav->addLabel($label);
      }

      foreach ($group_panels as $panel) {
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
    }

    $nav->selectFilter($selected);

    return $nav;
  }

  public function buildHeaderView($title) {
    $viewer = $this->getViewer();

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true)
      ->setHref($repository->getURI())
      ->setImage($repository->getProfileImageURI());

    if ($repository->isTracked()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Inactive'));
    }

    $doc_href = PhabricatorEnv::getDoclink(
      'Diffusion User Guide: Managing Repositories');

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('View Repository'))
        ->setHref($repository->getURI())
        ->setIcon('fa-code')
        ->setColor(PHUIButtonView::GREY));

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-book')
        ->setHref($doc_href)
        ->setText(pht('Help'))
        ->setColor(PHUIButtonView::GREY));

    return $header;
  }

  public function newTimeline(PhabricatorRepository $repository) {
    $timeline = $this->buildTransactionTimeline(
      $repository,
      new PhabricatorRepositoryTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $timeline;
  }


}
