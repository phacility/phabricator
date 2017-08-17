<?php

final class PhabricatorSettingsMainController
  extends PhabricatorController {

  private $user;
  private $builtinKey;
  private $preferences;

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

  private function isTemplate() {
    return ($this->builtinKey !== null);
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // Redirect "/panel/XYZ/" to the viewer's personal settings panel. This
    // was the primary URI before global settings were introduced and allows
    // generation of viewer-agnostic URIs for email and logged-out users.
    $panel = $request->getURIData('panel');
    if ($panel) {
      $panel = phutil_escape_uri($panel);
      $username = $viewer->getUsername();

      $panel_uri = "/user/{$username}/page/{$panel}/";
      $panel_uri = $this->getApplicationURI($panel_uri);
      return id(new AphrontRedirectResponse())->setURI($panel_uri);
    }

    $username = $request->getURIData('username');
    $builtin = $request->getURIData('builtin');

    $key = $request->getURIData('pageKey');

    if ($builtin) {
      $this->builtinKey = $builtin;

      $preferences = id(new PhabricatorUserPreferencesQuery())
        ->setViewer($viewer)
        ->withBuiltinKeys(array($builtin))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$preferences) {
        $preferences = id(new PhabricatorUserPreferences())
          ->attachUser(null)
          ->setBuiltinKey($builtin);
      }
    } else {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withUsernames(array($username))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();

      if (!$user) {
        return new Aphront404Response();
      }

      $preferences = PhabricatorUserPreferences::loadUserPreferences($user);
      $this->user = $user;
    }

    if (!$preferences) {
      return new Aphront404Response();
    }

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $preferences,
      PhabricatorPolicyCapability::CAN_EDIT);

    $this->preferences = $preferences;

    $panels = $this->buildPanels($preferences);
    $nav = $this->renderSideNav($panels);

    $key = $nav->selectFilter($key, head($panels)->getPanelKey());

    $panel = $panels[$key]
      ->setController($this)
      ->setNavigation($nav);

    $response = $panel->processRequest($request);
    if (($response instanceof AphrontResponse) ||
        ($response instanceof AphrontResponseProducerInterface)) {
      return $response;
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($panel->getPanelName());
    $crumbs->setBorder(true);

    if ($this->user) {
      $header_text = pht('Edit Settings (%s)', $user->getUserName());
    } else {
      $header_text = pht('Edit Global Settings');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    $title = $panel->getPanelName();

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFixed(true)
      ->setNavigation($nav)
      ->setMainColumn($response);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildPanels(PhabricatorUserPreferences $preferences) {
    $viewer = $this->getViewer();
    $panels = PhabricatorSettingsPanel::getAllDisplayPanels();

    $result = array();
    foreach ($panels as $key => $panel) {
      $panel
        ->setPreferences($preferences)
        ->setViewer($viewer);

      if ($this->user) {
        $panel->setUser($this->user);
      }

      if (!$panel->isEnabled()) {
        continue;
      }

      if ($this->isTemplate()) {
        if (!$panel->isTemplatePanel()) {
          continue;
        }
      } else {
        if (!$this->isSelf() && !$panel->isManagementPanel()) {
          continue;
        }

        if ($this->isSelf() && !$panel->isUserPanel()) {
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

    if ($this->isTemplate()) {
      $base_uri = 'builtin/'.$this->builtinKey.'/page/';
    } else {
      $user = $this->getUser();
      $base_uri = 'user/'.$user->getUsername().'/page/';
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
    if ($this->preferences) {
      $panels = $this->buildPanels($this->preferences);
      return $this->renderSideNav($panels)->getMenu();
    }
    return parent::buildApplicationMenu();
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
