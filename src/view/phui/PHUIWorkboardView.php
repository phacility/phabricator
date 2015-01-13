<?php

final class PHUIWorkboardView extends AphrontTagView {

  private $panels = array();
  private $fluidLayout = false;
  private $fluidishLayout = false;
  private $actions = array();

  public function addPanel(PHUIWorkpanelView $panel) {
    $this->panels[] = $panel;
    return $this;
  }

  public function setFluidLayout($layout) {
    $this->fluidLayout = $layout;
    return $this;
  }

  public function setFluidishLayout($layout) {
    $this->fluidishLayout = $layout;
    return $this;
  }

  public function addAction(PHUIIconView $action) {
    $this->actions[] = $action;
    return $this;
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-workboard-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-workboard-view-css');

    $action_list = null;
    if (!empty($this->actions)) {
      $items = array();
      foreach ($this->actions as $action) {
        $items[] = phutil_tag(
          'li',
            array(
              'class' => 'phui-workboard-action-item',
            ),
            $action);
      }
      $action_list = phutil_tag(
        'ul',
          array(
            'class' => 'phui-workboard-action-list',
          ),
          $items);
    }

    $view = new AphrontMultiColumnView();
    $view->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);
    if ($this->fluidLayout) {
      $view->setFluidLayout($this->fluidLayout);
    }
    if ($this->fluidishLayout) {
      $view->setFluidishLayout($this->fluidishLayout);
    }
    foreach ($this->panels as $panel) {
      $view->addColumn($panel);
    }

    $board = phutil_tag(
      'div',
        array(
          'class' => 'phui-workboard-view-shadow',
        ),
        $view);

    return array(
      $action_list,
      $board,
    );
  }
}
