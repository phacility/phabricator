<?php

final class PHUITextExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Text');
  }

  public function getDescription() {
    return pht('Simple styles for displaying text.');
  }

  public function renderExample() {

    $color1 = pht('This is RED.');
    $color2 = pht('This is ORANGE.');
    $color3 = pht('This is YELLOW.');
    $color4 = pht('This is GREEN.');
    $color5 = pht('This is BLUE.');
    $color6 = pht('This is INDIGO.');
    $color7 = pht('This is VIOLET.');
    $color8 = pht('This is WHITE.');
    $color9 = pht('This is BLACK.');

    $text1 = pht('This is BOLD.');
    $text2 = pht('This is Uppercase.');
    $text3 = pht('This is Stricken.');

    $content =
      array(
        id(new PHUITextView())
          ->setText($color1)
          ->addClass(PHUI::TEXT_RED),
        id(new PHUITextView())
          ->setText($color2)
          ->addClass(PHUI::TEXT_ORANGE),
        id(new PHUITextView())
          ->setText($color3)
          ->addClass(PHUI::TEXT_YELLOW),
        id(new PHUITextView())
          ->setText($color4)
          ->addClass(PHUI::TEXT_GREEN),
        id(new PHUITextView())
          ->setText($color5)
          ->addClass(PHUI::TEXT_BLUE),
        id(new PHUITextView())
          ->setText($color6)
          ->addClass(PHUI::TEXT_INDIGO),
        id(new PHUITextView())
          ->setText($color7)
          ->addClass(PHUI::TEXT_VIOLET),
        id(new PHUITextView())
          ->setText($color8)
          ->addClass(PHUI::TEXT_WHITE),
        id(new PHUITextView())
          ->setText($color9)
          ->addClass(PHUI::TEXT_BLACK),
      );

    $content2 =
      array(
        id(new PHUITextView())
          ->setText($text1)
          ->addClass(PHUI::TEXT_BOLD),
        id(new PHUITextView())
          ->setText($text2)
          ->addClass(PHUI::TEXT_UPPERCASE),
        id(new PHUITextView())
          ->setText($text3)
          ->addClass(PHUI::TEXT_STRIKE),
      );

    $layout1 = id(new PHUIBoxView())
      ->appendChild($content)
      ->setBorder(true)
      ->addPadding(PHUI::PADDING_MEDIUM);

    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Basic Colors'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $layout2 = id(new PHUIBoxView())
      ->appendChild($content2)
      ->setBorder(true)
      ->addPadding(PHUI::PADDING_MEDIUM);

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Basic Transforms'));

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($layout2)
      ->addMargin(PHUI::MARGIN_LARGE);

    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
        ));
  }
}
