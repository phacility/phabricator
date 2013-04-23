<?php

final class PHUIBoxExample extends PhabricatorUIExample {

  public function getName() {
    return 'Box';
  }

  public function getDescription() {
    return 'It\'s a fancy or non-fancy box. Put stuff in it.';
  }

  public function renderExample() {

    $content1 = 'Asmund and Signy';
    $content2 = 'The Cottager and his Cat';
    $content3 = 'Geirlug The King\'s Daughter';

    $layout1 =
      array(
        id(new PHUIBoxView())
          ->appendChild($content1),
        id(new PHUIBoxView())
          ->appendChild($content2),
        id(new PHUIBoxView())
          ->appendChild($content3));


    $layout2 =
      array(
        id(new PHUIBoxView())
          ->appendChild($content1)
          ->addMargin(PHUI::MARGIN_SMALL_LEFT),
        id(new PHUIBoxView())
          ->appendChild($content2)
          ->addMargin(PHUI::MARGIN_MEDIUM_LEFT)
          ->addMargin(PHUI::MARGIN_MEDIUM_TOP),
        id(new PHUIBoxView())
          ->appendChild($content3)
          ->addMargin(PHUI::MARGIN_LARGE_LEFT)
          ->addMargin(PHUI::MARGIN_LARGE_TOP));

    $layout3 =
      array(
        id(new PHUIBoxView())
          ->appendChild($content1)
          ->setShadow(true)
          ->addPadding(PHUI::PADDING_SMALL)
          ->addMargin(PHUI::MARGIN_LARGE_BOTTOM),
        id(new PHUIBoxView())
          ->appendChild($content2)
          ->setShadow(true)
          ->addPadding(PHUI::PADDING_MEDIUM)
          ->addMargin(PHUI::MARGIN_LARGE_BOTTOM),
        id(new PHUIBoxView())
          ->appendChild($content3)
          ->setShadow(true)
          ->addPadding(PHUI::PADDING_LARGE)
          ->addMargin(PHUI::MARGIN_LARGE_BOTTOM));

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Plain Box'));

    $head2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Plain Box with space'));

    $head3 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Shadow Box with space'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap2 = id(new PHUIBoxView())
      ->appendChild($layout2)
      ->addMargin(PHUI::MARGIN_LARGE);

    $wrap3 = id(new PHUIBoxView())
      ->appendChild($layout3)
      ->addMargin(PHUI::MARGIN_LARGE);

    return phutil_tag(
      'div',
        array(),
        array(
          $head1,
          $wrap1,
          $head2,
          $wrap2,
          $head3,
          $wrap3
        ));
        }
}
