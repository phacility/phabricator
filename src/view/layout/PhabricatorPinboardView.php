<?php

final class PhabricatorPinboardView extends AphrontView {

  private $items = array();

  public function addItem(PhabricatorPinBoardItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-pinboard-view-css');

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-pinboard-view',
      ),
      $this->renderSingleView($this->items));
  }

}
