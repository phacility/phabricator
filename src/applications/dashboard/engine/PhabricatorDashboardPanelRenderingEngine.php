<?php

final class PhabricatorDashboardPanelRenderingEngine extends Phobject {

  private $panel;
  private $viewer;

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

    return $panel_type->renderPanel($viewer, $panel);
  }

  private function renderErrorPanel($title, $body) {
    return id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors(array($body));
  }

}
