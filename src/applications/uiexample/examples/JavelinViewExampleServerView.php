<?php

final class JavelinViewExampleServerView extends AphrontView {
  public function render() {
    return phutil_render_tag(
      'div',
      array('class' => 'server-view'),
      $this->renderChildren()
    );
  }
}
