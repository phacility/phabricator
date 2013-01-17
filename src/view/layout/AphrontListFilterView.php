<?php

final class AphrontListFilterView extends AphrontView {

  public function render() {
    require_celerity_resource('aphront-list-filter-view-css');
    return
      '<table class="aphront-list-filter-view">'.
        '<tr>'.
          '<td class="aphront-list-filter-view-controls">'.
            $this->renderChildren().
          '</td>'.
        '</tr>'.
      '</table>';
  }

}
