<?php

final class PhabricatorWorkboardView extends AphrontView {

  private $panels = array();
  private $fluidLayout = false;
  private $actions = array();

  public function addPanel(PhabricatorWorkpanelView $panel) {
    $this->panels[] = $panel;
    return $this;
  }

  public function setFluidLayout($layout) {
    $this->fluidLayout = $layout;
    return $this;
  }

  public function addAction(PHUIIconView $action) {
    $this->actions[] = $action;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-workboard-view-css');

    $action_list = null;
    if (!empty($this->actions)) {
      $items = array();
      foreach ($this->actions as $action) {
        $items[] = phutil_tag(
          'li',
            array(
              'class' => 'phabricator-workboard-action-item'
            ),
            $action);
      }
      $action_list = phutil_tag(
        'ul',
          array(
            'class' => 'phabricator-workboard-action-list'
          ),
          $items);
    }

    $view = new AphrontMultiColumnView();
    $view->setGutter(AphrontMultiColumnView::GUTTER_MEDIUM);
    $view->setFluidLayout($this->fluidLayout);
    foreach ($this->panels as $panel) {
      $view->addColumn($panel);
    }

    $board = phutil_tag(
      'div',
        array(
          'class' => 'phabricator-workboard-view-shadow'
        ),
        $view);

    return phutil_tag(
      'div',
        array(
          'class' => 'phabricator-workboard-view'
        ),
        array(
          $action_list,
          $board
        ));
  }
}
