<?php

final class AphrontListFilterView extends AphrontView {

  public function render() {
    $contents = $this->renderChildren();

    if (!$contents) {
      return null;
    }

    require_celerity_resource('aphront-list-filter-view-css');
    return hsprintf(
      '<div class="aphront-filter-table-wrapper">'.
        '<table class="aphront-list-filter-view">'.
          '<tr>'.
            '<td class="aphront-list-filter-view-controls">%s</td>'.
          '</tr>'.
        '</table>'.
      '</div>',
      $contents);
  }

}
