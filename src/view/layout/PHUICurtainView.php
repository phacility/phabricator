<?php

final class PHUICurtainView extends AphrontTagView {

  private $actionList;
  private $panels = array();

  public function addAction(PhabricatorActionView $action) {
    $this->getActionList()->addAction($action);
    return $this;
  }

  public function addPanel(PHUICurtainPanelView $curtain_panel) {
    $this->panels[] = $curtain_panel;
    return $this;
  }

  public function newPanel() {
    $panel = new PHUICurtainPanelView();
    $this->addPanel($panel);

    // By default, application panels go at the top of the curtain, above
    // extension panels.
    $panel->setOrder(1000);

    return $panel;
  }

  public function setActionList(PhabricatorActionListView $action_list) {
    $this->actionList = $action_list;
    return $this;
  }

  public function getActionList() {
    return $this->actionList;
  }

  protected function canAppendChild() {
    return false;
  }

  protected function getTagContent() {
    $action_list = $this->actionList;

    require_celerity_resource('phui-curtain-view-css');

    $panels = $this->renderPanels();

    $box = id(new PHUIObjectBoxView())
      ->appendChild($action_list)
      ->appendChild($panels)
      ->addClass('phui-two-column-properties');

    // We want to hide this UI on mobile if there are no child panels
    if (!$panels) {
      $box->addClass('curtain-no-panels');
    }

    return $box;
  }

  private function renderPanels() {
    $panels = $this->panels;
    $panels = msortv($panels, 'getOrderVector');

    return $panels;
  }


}
