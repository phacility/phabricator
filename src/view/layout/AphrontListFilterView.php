<?php

final class AphrontListFilterView extends AphrontView {

  public function render() {
    require_celerity_resource('aphront-list-filter-view-css');
    return hsprintf(
      '<div class="aphront-filter-table-wrapper">'.
        '<table class="aphront-list-filter-view">'.
          '<tr>'.
            '<td class="aphront-list-filter-view-controls">%s</td>'.
          '</tr>'.
        '</table>'.
      '</div>',
      $this->renderChildren());
  }

}
