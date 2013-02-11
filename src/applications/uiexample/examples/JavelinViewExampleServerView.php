<?php

final class JavelinViewExampleServerView extends AphrontView {

  public function render() {
    return phutil_tag(
      'div',
      array(
        'class' => 'server-view',
      ),
      $this->renderChildren());
  }

}
