<?php

abstract class PhabricatorApplicationConfigurationPanel
  extends Phobject {

  private $viewer;
  private $application;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function getPanelURI($path = null) {
    $app_key = get_class($this->getApplication());
    $panel_key = $this->getPanelKey();
    $base = "/applications/panel/{$app_key}/{$panel_key}/";
    return $base.ltrim($path, '/');
  }

  /**
   * Return a short, unique string key which identifies this panel.
   *
   * This key is used in URIs. Good values might be "email" or "files".
   */
  abstract public function getPanelKey();

  abstract public function shouldShowForApplication(
    PhabricatorApplication $application);

  abstract public function buildConfigurationPagePanel();
  abstract public function handlePanelRequest(
    AphrontRequest $request,
    PhabricatorController $controller);

  public static function loadAllPanels() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getPanelKey')
      ->execute();
  }

  public static function loadAllPanelsForApplication(
    PhabricatorApplication $application) {
    $panels = self::loadAllPanels();

    $application_panels = array();
    foreach ($panels as $key => $panel) {
      if (!$panel->shouldShowForApplication($application)) {
        continue;
      }
      $application_panels[$key] = $panel;
    }

    return $application_panels;
  }

}
