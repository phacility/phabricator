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

    // By default, application panels go at the bottom of the curtain, below
    // extension panels.
    $panel->setOrder(100000);

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

    return id(new PHUIObjectBoxView())
      ->appendChild($action_list)
      ->appendChild($panels)
      ->addClass('phui-two-column-properties');
  }

  private function renderPanels() {
    $panels = $this->panels;
    $panels = msortv($panels, 'getOrderVector');

    return $panels;
  }


}
