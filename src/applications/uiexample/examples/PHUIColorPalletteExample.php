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
      'f4dddb' => '83% Red',
      'e67e22' => 'Base Orange',
      'f7e2d4' => '83% Orange',
      'f1c40f' => 'Base Yellow',
      'fdf5d4' => '83% Yellow',
      '139543' => 'Base Green',
      'd7eddf' => '83% Green',
      '2980b9' => 'Base Blue',
      'daeaf3' => '83% Blue',
      '3498db' => 'Sky Base',
      'ddeef9' => '83% Sky',
      'c6539d' => 'Base Indigo',
      'f5e2ef' => '83% Indigo',
      '8e44ad' => 'Base Violet',
      'ecdff1' => '83% Violet'
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
    $url = array();
    foreach ($colors as $color => $name) {
      $url[] = $color;
      $c_column[] = phutil_tag(
        'div',
        array(
          'style' => 'background-color: #'.$color.';',
          'class' => 'pl'),
        $name.' #'.$color);
    }

    $color_url = phutil_tag(
      'a',
      array(
        'href' => 'http://color.hailpixel.com/#'.implode(',', $url),
        'class' => 'button grey mlb'),
      'Color Palette');

    $layout1 = id(new PHUIBoxView())
      ->appendChild($d_column)
      ->setShadow(true)
      ->addPadding(PHUI::PADDING_LARGE);

    $layout2 = id(new PHUIBoxView())
      ->appendChild($color_url)
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
