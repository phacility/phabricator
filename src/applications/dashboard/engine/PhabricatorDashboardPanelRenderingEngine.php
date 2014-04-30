<?php

final class PhabricatorDashboardPanelRenderingEngine extends Phobject {

  private $panel;
  private $viewer;
  private $enableAsyncRendering;

  /**
   * Allow the engine to render the panel via Ajax.
   */
  public function setEnableAsyncRendering($enable) {
    $this->enableAsyncRendering = $enable;
    return $this;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setPanel(PhabricatorDashboardPanel $panel) {
    $this->panel = $panel;
    return $this;
  }

  public function renderPanel() {
    $panel = $this->panel;
    $viewer = $this->viewer;

    if (!$panel) {
      return $this->renderErrorPanel(
        pht('Missing Panel'),
        pht('This panel does not exist.'));
    }

    $panel_type = $panel->getImplementation();
    if (!$panel_type) {
      return $this->renderErrorPanel(
        $panel->getName(),
        pht(
          'This panel has type "%s", but that panel type is not known to '.
          'Phabricator.',
          $panel->getPanelType()));
    }

    if ($this->enableAsyncRendering) {
      if ($panel_type->shouldRenderAsync()) {
        return $this->renderAsyncPanel($panel);
      }
    }


    return $panel_type->renderPanel($viewer, $panel);
  }

  private function renderErrorPanel($title, $body) {
    return id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors(array($body));
  }

  private function renderAsyncPanel(PhabricatorDashboardPanel $panel) {
    $panel_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'dashboard-async-panel',
      array(
        'panelID' => $panel_id,
        'uri' => '/dashboard/panel/render/'.$panel->getID().'/',
      ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText($panel->getName())
      ->setID($panel_id)
      ->appendChild(pht('Loading...'));
  }

}
