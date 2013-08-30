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
      'c0392b' => 'Base Red {$red}',
      'f4dddb' => '83% Red {$lightred}',
      'e67e22' => 'Base Orange {$orange}',
      'f7e2d4' => '83% Orange {$lightorange}',
      'f1c40f' => 'Base Yellow {$yellow}',
      'fdf5d4' => '83% Yellow {$lightyellow}',
      '139543' => 'Base Green {$green}',
      'd7eddf' => '83% Green {$lightgreen}',
      '2980b9' => 'Base Blue {$blue}',
      'daeaf3' => '83% Blue {$lightblue}',
      '3498db' => 'Sky Base {$sky}',
      'ddeef9' => '83% Sky {$lightsky}',
      'c6539d' => 'Base Indigo {$indigo}',
      'f5e2ef' => '83% Indigo {$lightindigo}',
      '8e44ad' => 'Base Violet {$violet}',
      'ecdff1' => '83% Violet {$lightviolet}'
    );

    $greys = array(
      'BBC0CC' => 'Light Grey Border {$lightgreyborder}',
      'A2A6B0' => 'Grey Border {$greyborder}',
      '676A70' => 'Dark Grey Border {$darkgreyborder}',
      '92969D' => 'Light Grey Text {$lightgreytext}',
      '57595E' => 'Grey Text {$greytext}',
      '4B4D51' => 'Dark Grey Text [$darkgreytext}',
      'F7F7F7' => 'Light Grey Background {$lightgreybackground}',
      'EBECEE' => 'Grey Background {$greybackground}',
    );

    $d_column = array();
    foreach ($greys as $color => $name) {
      $d_column[] = phutil_tag(
        'div',
        array(
          'style' => 'background-color: #'.$color.';',
          'class' => 'pl'),
        $name.' #'.$color);
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
      ->setHeader(pht('Greys'));

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
