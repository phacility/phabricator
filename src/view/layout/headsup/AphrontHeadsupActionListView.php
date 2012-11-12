<?php

final class AphrontHeadsupActionListView extends AphrontView {

  private $actions;

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function render() {

    require_celerity_resource('aphront-headsup-action-list-view-css');

    $actions = array();
    foreach ($this->actions as $action_view) {
      $actions[] = $action_view->render();
    }
    $actions = implode("\n", $actions);

    return
      '<div class="aphront-headsup-action-list">'.
        $actions.
      '</div>';
  }

}
