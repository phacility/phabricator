<?php

final class PHUITextExample extends PhabricatorUIExample {

  public function getName() {
    return 'Text';
  }

  public function getDescription() {
    return 'Simple styles for displaying text.';
  }

  public function renderExample() {

    $color1 = 'This is RED. ';
    $color2 = 'This is ORANGE. ';
    $color3 = 'This is YELLOW. ';
    $color4 = 'This is GREEN. ';
    $color5 = 'This is BLUE. ';
    $color6 = 'This is INDIGO. ';
    $color7 = 'This is VIOLET. ';
    $color8 = 'This is WHITE. ';
    $color9 = 'This is BLACK. ';

    $text1 = 'This is BOLD. ';
    $text2 = 'This is Uppercase. ';
    $text3 = 'This is Stricken.';

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
          ->addClass(PHUI::TEXT_BLACK));

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
          ->addClass(PHUI::TEXT_STRIKE));

    $layout1 = id(new PHUIBoxView())
      ->appendChild($content)
            ->setShadow(true)
      ->addPadding(PHUI::PADDING_MEDIUM);

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Basic Colors'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $layout2 = id(new PHUIBoxView())
      ->appendChild($content2)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_MEDIUM);

    $head2 = id(new PhabricatorHeaderView())
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
          $wrap2
        ));
        }
}
