<?php

final class PHUILauncherView
  extends AphrontTagView {

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    $classes = array();

    $classes[] = 'phui-launcher-view';

    return array(
      'class' => implode(' ', $classes),
    );
  }

}
