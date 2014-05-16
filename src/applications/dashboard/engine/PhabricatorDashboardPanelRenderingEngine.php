<?php

final class PhabricatorDashboardPanelRenderingEngine extends Phobject {

  private $panel;
  private $viewer;
  private $enableAsyncRendering;
  private $parentPanelPHIDs;

  /**
   * Allow the engine to render the panel via Ajax.
   */
  public function setEnableAsyncRendering($enable) {
    $this->enableAsyncRendering = $enable;
    return $this;
  }

  public function setParentPanelPHIDs(array $parents) {
    $this->parentPanelPHIDs = $parents;
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

    try {
      $this->detectRenderingCycle($panel);

      if ($this->enableAsyncRendering) {
        if ($panel_type->shouldRenderAsync()) {
          return $this->renderAsyncPanel($panel);
        }
      }

      return $panel_type->renderPanel($viewer, $panel);
    } catch (Exception $ex) {
      return $this->renderErrorPanel(
        $panel->getName(),
        pht(
          '%s: %s',
          phutil_tag('strong', array(), get_class($ex)),
          $ex->getMessage()));
    }
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
        'parentPanelPHIDs' => $this->parentPanelPHIDs,
        'uri' => '/dashboard/panel/render/'.$panel->getID().'/',
      ));

    return id(new PHUIObjectBoxView())
      ->addSigil('dashboard-panel')
      ->setMetadata(array(
        'objectPHID' => $panel->getPHID()))
      ->setHeaderText($panel->getName())
      ->setID($panel_id)
      ->appendChild(pht('Loading...'));
  }

  /**
   * Detect graph cycles in panels, and deeply nested panels.
   *
   * This method throws if the current rendering stack is too deep or contains
   * a cycle. This can happen if you embed layout panels inside each other,
   * build a big stack of panels, or embed a panel in remarkup inside another
   * panel. Generally, all of this stuff is ridiculous and we just want to
   * shut it down.
   *
   * @param PhabricatorDashboardPanel Panel being rendered.
   * @return void
   */
  private function detectRenderingCycle(PhabricatorDashboardPanel $panel) {
    if ($this->parentPanelPHIDs === null) {
      throw new Exception(
        pht(
          'You must call setParentPanelPHIDs() before rendering panels.'));
    }

    $max_depth = 4;
    if (count($this->parentPanelPHIDs) >= $max_depth) {
      throw new Exception(
        pht(
          'To render more than %s levels of panels nested inside other '.
          'panels, purchase a subscription to Phabricator Gold.',
          new PhutilNumber($max_depth)));
    }

    if (in_array($panel->getPHID(), $this->parentPanelPHIDs)) {
      throw new Exception(
        pht(
          'You awake in a twisting maze of mirrors, all alike. '.
          'You are likely to be eaten by a graph cycle. '.
          'Should you escape alive, you resolve to be more careful about '.
          'putting dashboard panels inside themselves.'));
    }
  }


}
