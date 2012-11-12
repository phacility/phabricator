<?php

final class AphrontListFilterView extends AphrontView {

  private $buttons = array();

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-list-filter-view-css');

    $buttons = null;
    if ($this->buttons) {
      $buttons =
        '<td class="aphront-list-filter-view-buttons">'.
          implode("\n", $this->buttons).
        '</td>';
    }

    return
      '<table class="aphront-list-filter-view">'.
        '<tr>'.
          '<td class="aphront-list-filter-view-controls">'.
            $this->renderChildren().
          '</td>'.
          $buttons.
        '</tr>'.
      '</table>';
  }

}
