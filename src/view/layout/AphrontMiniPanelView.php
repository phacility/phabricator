<?php

final class AphrontMiniPanelView extends AphrontView {

  public function render() {
    return phutil_tag(
      'div',
      array('class' => 'aphront-mini-panel-view'),
      $this->renderChildren());
  }

}
