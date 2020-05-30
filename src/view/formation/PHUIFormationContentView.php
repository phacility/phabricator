<?php

final class PHUIFormationContentView
  extends PHUIFormationColumnView {

  public function getIsControlColumn() {
    return true;
  }

  public function render() {
    require_celerity_resource('phui-formation-view-css');

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-formation-view-content',
      ),
      $this->renderChildren());
  }

}
