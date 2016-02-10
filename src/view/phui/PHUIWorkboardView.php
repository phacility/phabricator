<?php

final class PHUIWorkboardView extends AphrontTagView {

  private $panels = array();
  private $actions = array();

  public function addPanel(PHUIWorkpanelView $panel) {
    $this->panels[] = $panel;
    return $this;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-workboard-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-workboard-view-css');

    $view = new AphrontMultiColumnView();
    $view->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);
    foreach ($this->panels as $panel) {
      $view->addColumn($panel);
    }

    $board = javelin_tag(
      'div',
      array(
        'class' => 'phui-workboard-view-shadow',
        'sigil' => 'workboard-shadow lock-scroll-y-while-dragging',
      ),
      $view);

    return $board;
  }
}
