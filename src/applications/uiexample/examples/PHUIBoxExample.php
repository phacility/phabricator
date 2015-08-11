<?php

final class PHUIBoxExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Box');
  }

  public function getDescription() {
    return pht("It's a fancy or non-fancy box. Put stuff in it.");
  }

  public function renderExample() {

    $content1 = 'Asmund and Signy';
    $content2 = 'The Cottager and his Cat';
    $content3 = "Geirlug The King's Daughter";

    $layout1 =
      array(
        id(new PHUIBoxView())
          ->appendChild($content1),
        id(new PHUIBoxView())
          ->appendChild($content2),
        id(new PHUIBoxView())
          ->appendChild($content3),
      );


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
          ->addMargin(PHUI::MARGIN_LARGE_TOP),
      );

    $layout3 =
      array(
        id(new PHUIBoxView())
          ->appendChild($content1)
          ->setBorder(true)
          ->addPadding(PHUI::PADDING_SMALL)
          ->addMargin(PHUI::MARGIN_LARGE_BOTTOM),
        id(new PHUIBoxView())
          ->appendChild($content2)
          ->setBorder(true)
          ->addPadding(PHUI::PADDING_MEDIUM)
          ->addMargin(PHUI::MARGIN_LARGE_BOTTOM),
        id(new PHUIBoxView())
          ->appendChild($content3)
          ->setBorder(true)
          ->addPadding(PHUI::PADDING_LARGE)
          ->addMargin(PHUI::MARGIN_LARGE_BOTTOM),
      );

    $image = id(new PHUIIconView())
          ->setIconFont('fa-heart');
    $button = id(new PHUIButtonView())
        ->setTag('a')
        ->setColor(PHUIButtonView::SIMPLE)
        ->setIcon($image)
        ->setText(pht('Such Wow'))
        ->addClass(PHUI::MARGIN_SMALL_RIGHT);

    $badge1 = id(new PHUIBadgeMiniView())
      ->setIcon('fa-bug')
      ->setHeader(pht('Bugmeister'));

    $badge2 = id(new PHUIBadgeMiniView())
      ->setIcon('fa-heart')
      ->setHeader(pht('Funder'))
      ->setQuality(PHUIBadgeView::UNCOMMON);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Fancy Box'))
      ->addActionLink($button)
      ->setSubheader(pht('Much Features'))
      ->addBadge($badge1)
      ->addBadge($badge2);

    $obj4 = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild(id(new PHUIBoxView())
        ->addPadding(PHUI::PADDING_MEDIUM)
        ->appendChild(pht('Such Fancy, Nice Box, Many Corners.')));

    $head1 = id(new PHUIHeaderView())
      ->setHeader(pht('Plain Box'));

    $head2 = id(new PHUIHeaderView())
      ->setHeader(pht('Plain Box with space'));

    $head3 = id(new PHUIHeaderView())
      ->setHeader(pht('Border Box with space'));

    $head4 = id(new PHUIHeaderView())
      ->setHeader(pht('PHUIObjectBoxView'));

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
          $wrap3,
          $head4,
          $obj4,
        ));
      }
}
