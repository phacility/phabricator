<?php

final class PHUIColorPalletteExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Colors');
  }

  public function getDescription() {
    return pht('A Standard Palette of Colors for use.');
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
      '6e5cb6' => 'Base Indigo {$indigo}',
      'eae6f7' => '83% Indigo {$lightindigo}',
      'da49be' => 'Base Pink {$pink}',
      'fbeaf8' => '83% Pink {$lightpink}',
      '8e44ad' => 'Base Violet {$violet}',
      'ecdff1' => '83% Violet {$lightviolet}',
    );

    $greys = array(
      'C7CCD9' => 'Light Grey Border {$lightgreyborder}',
      'A1A6B0' => 'Grey Border {$greyborder}',
      '676A70' => 'Dark Grey Border {$darkgreyborder}',
      '92969D' => 'Light Grey Text {$lightgreytext}',
      '74777D' => 'Grey Text {$greytext}',
      '4B4D51' => 'Dark Grey Text {$darkgreytext}',
      'F7F7F7' => 'Light Grey Background {$lightgreybackground}',
      'EBECEE' => 'Grey Background {$greybackground}',
      'DFE0E2' => 'Dark Grey Background {$darkgreybackground}',
    );

    $blues = array(
      'DDE8EF' => 'Thin Blue Border {$thinblueborder}',
      'BFCFDA' => 'Light Blue Border {$lightblueborder}',
      '95A6C5' => 'Blue Border {$blueborder}',
      '626E82' => 'Dark Blue Border {$darkblueborder}',
      'F8F9FC' => 'Light Blue Background {$lightbluebackground}',
      'DAE7FF' => 'Blue Background {$bluebackground}',
      '8C98B8' => 'Light Blue Text {$lightbluetext}',
      '6B748C' => 'Blue Text {$bluetext}',
      '464C5C' => 'Dark Blue Text {$darkbluetext}',
    );

    $d_column = array();
    foreach ($greys as $color => $name) {
      $d_column[] = phutil_tag(
        'div',
        array(
          'style' => 'background-color: #'.$color.';',
          'class' => 'pl',
        ),
        $name.' #'.$color);
    }

    $b_column = array();
    foreach ($blues as $color => $name) {
      $b_column[] = phutil_tag(
        'div',
        array(
          'style' => 'background-color: #'.$color.';',
          'class' => 'pl',
        ),
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
          'class' => 'pl',
        ),
        $name.' #'.$color);
    }

    $color_url = phutil_tag(
      'a',
      array(
        'href' => 'http://color.hailpixel.com/#'.implode(',', $url),
        'class' => 'button grey mlb',
      ),
      pht('Color Palette'));

    $wrap1 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Greys'))
      ->appendChild($d_column);

    $wrap2 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Blues'))
      ->appendChild($b_column);

    $wrap3 = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Colors'))
      ->appendChild($c_column);

    return phutil_tag(
      'div',
        array(),
        array(
          $wrap1,
          $wrap2,
          $wrap3,
        ));
  }
}
