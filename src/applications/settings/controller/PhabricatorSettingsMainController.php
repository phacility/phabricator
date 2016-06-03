<?php

final class PhabricatorSettingsMainController
  extends PhabricatorController {

  private $user;

  private function getUser() {
    return $this->user;
  }

  private function isSelf() {
    $user = $this->getUser();
    if (!$user) {
      return false;
    }

    $user_phid = $user->getPHID();

    $viewer_phid = $this->getViewer()->getPHID();
    return ($viewer_phid == $user_phid);
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $key = $request->getURIData('key');

    if ($id) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();

      if (!$user) {
        return new Aphront404Response();
      }

      $this->user = $user;
    } else {
      $this->user = $viewer;
    }

    $panels = $this->buildPanels();
    $nav = $this->renderSideNav($panels);

    $key = $nav->selectFilter($key, head($panels)->getPanelKey());

    $panel = $panels[$key]
      ->setUser($this->getUser())
      ->setViewer($viewer)
      ->setController($this)
      ->setNavigation($nav);

    $response = $panel->processRequest($request);
    if (($response instanceof AphrontResponse) ||
        ($response instanceof AphrontResponseProducerInterface)) {
      return $response;
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($panel->getPanelName());

    $title = $panel->getPanelName();

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn($response);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildPanels() {
    $viewer = $this->getViewer();
    $panels = PhabricatorSettingsPanel::getAllDisplayPanels();

    $result = array();
    foreach ($panels as $key => $panel) {
      $panel
        ->setViewer($viewer)
        ->setUser($this->user);

      if (!$panel->isEnabled()) {
        continue;
      }

      if (!$this->isSelf()) {
        if (!$panel->isEditableByAdministrators()) {
          continue;
        }
      }

      if (!empty($result[$key])) {
        throw new Exception(pht(
          "Two settings panels share the same panel key ('%s'): %s, %s.",
          $key,
          get_class($panel),
          get_class($result[$key])));
      }

      $result[$key] = $panel;
    }

    if (!$result) {
      throw new Exception(pht('No settings panels are available.'));
    }

    return $result;
  }

  private function renderSideNav(array $panels) {
    $nav = new AphrontSideNavFilterView();

    if ($this->isSelf()) {
      $base_uri = 'panel/';
    } else {
      $base_uri = $this->getUser()->getID().'/panel/';
    }

    $nav->setBaseURI(new PhutilURI($this->getApplicationURI($base_uri)));

    $group_key = null;
    foreach ($panels as $panel) {
      if ($panel->getPanelGroupKey() != $group_key) {
        $group_key = $panel->getPanelGroupKey();
        $group = $panel->getPanelGroup();
        $nav->addLabel($group->getPanelGroupName());
      }

      $nav->addFilter($panel->getPanelKey(), $panel->getPanelName());
    }

    return $nav;
  }

  public function buildApplicationMenu() {
    $panels = $this->buildPanels();
    return $this->renderSideNav($panels)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $user = $this->getUser();
    if (!$this->isSelf() && $user) {
      $username = $user->getUsername();
      $crumbs->addTextCrumb($username, "/p/{$username}/");
    }

    return $crumbs;
  }

}
