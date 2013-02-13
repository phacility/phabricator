<?php

final class PhabricatorButtonsExample extends PhabricatorUIExample {

  public function getName() {
    return 'Buttons';
  }

  public function getDescription() {
    return hsprintf('Use <tt>&lt;button&gt;</tt> to render buttons.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $colors = array('', 'green', 'grey', 'black', 'disabled');
    $sizes = array('', 'small');
    $tags = array('a', 'button');

    $view = array();
    foreach ($tags as $tag) {
      foreach ($colors as $color) {
        foreach ($sizes as $size) {
          $class = implode(' ', array($color, $size));

          if ($tag == 'a') {
            $class .= ' button';
          }

          $view[] = phutil_tag(
            $tag,
            array(
              'class' => $class,
            ),
            ucwords($size.' '.$color.' '.$tag));

          $view[] = hsprintf('<br /><br />');
        }
      }
    }

    return phutil_tag('div', array('style' => 'margin: 1em 2em;'), $view);
  }
}
