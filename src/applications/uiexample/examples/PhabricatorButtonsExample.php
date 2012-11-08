<?php

final class PhabricatorButtonsExample extends PhabricatorUIExample {

  public function getName() {
    return 'Buttons';
  }

  public function getDescription() {
    return 'Use <tt>&lt;button&gt;</tt> to render buttons.';
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

          $view[] = phutil_render_tag(
            $tag,
            array(
              'class' => $class,
            ),
            phutil_escape_html(ucwords($size.' '.$color.' '.$tag)));

          $view[] = '<br /><br />';
        }
      }
    }

    return '<div style="margin: 1em 2em;">'.implode('', $view).'</div>';
  }
}
