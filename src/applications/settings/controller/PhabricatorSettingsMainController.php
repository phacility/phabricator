<?php

final class PhabricatorSettingsMainController
  extends PhabricatorController {

  private $user;

  private function getUser() {
    return $this->user;
  }

  private function isSelf() {
    $viewer_phid = $this->getViewer()->getPHID();
    $user_phid = $this->getUser()->getPHID();
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

    $panel = $panels[$key];
    $panel->setUser($this->getUser());
    $panel->setViewer($viewer);

    $response = $panel->processRequest($request);
    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $crumbs = $this->buildApplicationCrumbs();
    if (!$this->isSelf()) {
      $crumbs->addTextCrumb(
        $this->getUser()->getUsername(),
        '/p/'.$this->getUser()->getUsername().'/');
    }
    $crumbs->addTextCrumb($panel->getPanelName());
    $nav->appendChild($response);

    $title = $panel->getPanelName();

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav);

  }

  private function buildPanels() {
    $panels = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorSettingsPanel')
      ->setExpandMethod('buildPanels')
      ->setUniqueMethod('getPanelKey')
      ->execute();

    $result = array();
    foreach ($panels as $key => $panel) {
      $panel->setUser($this->user);

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

    $result = msort($result, 'getPanelSortKey');

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

    $group = null;
    foreach ($panels as $panel) {
      if ($panel->getPanelGroup() != $group) {
        $group = $panel->getPanelGroup();
        $nav->addLabel($group);
      }

      $nav->addFilter($panel->getPanelKey(), $panel->getPanelName());
    }

    return $nav;
  }

  public function buildApplicationMenu() {
    $panels = $this->buildPanels();
    return $this->renderSideNav($panels)->getMenu();
  }

}
