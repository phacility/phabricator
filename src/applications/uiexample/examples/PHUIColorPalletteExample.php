<?php

final class PHUIColorPalletteExample extends PhabricatorUIExample {

  public function getName() {
    return 'Colors';
  }

  public function getDescription() {
    return 'A Standard Palette of Colors for use.';
  }

  public function renderExample() {

    $colors = array(
      'c0392b' => 'Base Red',
      'd35400' => 'Base Orange',
      'f1c40f' => 'Base Yellow',
      '27ae60' => 'Base Green',
      '298094' => 'Base Blue',
      'c6539d' => 'Base Indigo',
      '8e44ad' => 'Base Violet',
    );

    $darks = array(
      'ecf0f1',
      'bdc3c7',
      '95a5a6',
      '7f8c8d',
      '34495e',
      '2c3e50');

    $d_column = array();
    foreach ($darks as $dark) {
      $d_column[] = phutil_tag(
        'div',
        array(
          'style' => 'background-color: #'.$dark.';',
          'class' => 'pl'),
        '#'.$dark);
    }

    $c_column = array();
    foreach ($colors as $color => $name) {
      $c_column[] = phutil_tag(
        'div',
        array(
          'style' => 'background-color: #'.$color.';',
          'class' => 'pl'),
        $name.' #'.$color);
    }

    $layout1 = id(new PHUIBoxView())
      ->appendChild($d_column)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_LARGE);

    $layout2 = id(new PHUIBoxView())
      ->appendChild($c_column)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_LARGE);

    $head1 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Darks'));

    $wrap1 = id(new PHUIBoxView())
      ->appendChild($layout1)
      ->addMargin(PHUI::MARGIN_LARGE);

    $head2 = id(new PhabricatorHeaderView())
      ->setHeader(pht('Colors'));

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
