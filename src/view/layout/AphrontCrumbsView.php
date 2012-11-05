<?php

final class AphrontCrumbsView extends AphrontView {

  private $crumbs = array();

  public function setCrumbs(array $crumbs) {
    $this->crumbs = $crumbs;
    return $this;
  }

  public function render() {

    require_celerity_resource('aphront-crumbs-view-css');

    $out = array();
    foreach ($this->crumbs as $crumb) {
      $out[] = $this->renderSingleView($crumb);
    }
    $out = implode(
      '<span class="aphront-crumbs-spacer">'.
        "\xC2\xBB".
      '</span>',
      $out);

    return
      '<div class="aphront-crumbs-view">'.
        '<div class="aphront-crumbs-content">'.
          $out.
        '</div>'.
      '</div>';
  }

}
