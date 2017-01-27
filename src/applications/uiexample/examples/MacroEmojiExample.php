<?php

final class MacroEmojiExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Emoji Support');
  }

  public function getDescription() {
    return pht('Shiny happy people holding hands');
  }

  public function renderExample() {

    $raw = id(new PhabricatorEmojiRemarkupRule())
      ->markupEmojiJSON();

    $json = phutil_json_decode($raw);

    $content = array();
    foreach ($json as $shortname => $hex) {

      $display_name = ' '.$hex.' '.$shortname;

      $content[] = phutil_tag(
        'div',
        array(
          'class' => 'ms grouped',
          'style' => 'width: 240px; height: 24px; float: left;',
        ),
        $display_name);

    }

    $wrap = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Emojis'))
      ->addClass('grouped')
      ->appendChild($content);

    return phutil_tag(
      'div',
        array(),
        array(
          $wrap,
        ));
      }
}
