<?php

final class PhabricatorAphrontBarUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Bars');
  }

  public function getDescription() {
    return pht('Like fractions, but more horizontal.');
  }

  public function renderExample() {
    $out = array();
    $out[] = $this->renderRainbow();
    return $out;
  }

  private function wrap($title, $thing) {
    $thing = phutil_tag_div('ml grouped', $thing);
    return id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($thing);
  }

  private function renderRainbow() {
    $colors = array(
      'red',
      'orange',
      'yellow',
      'green',
      'blue',
      'indigo',
      'violet',
    );

    $labels = array(
      pht('Empty'),
      pht('Red'),
      pht('Orange'),
      pht('Yellow'),
      pht('Green'),
      pht('Blue'),
      pht('Indigo'),
      pht('Violet'),
    );

    $bars = array();

    for ($jj = -1; $jj < count($colors); $jj++) {
      $bar = id(new PHUISegmentBarView())
        ->setLabel($labels[$jj + 1]);
      for ($ii = 0; $ii <= $jj; $ii++) {
        $bar->newSegment()
          ->setWidth(1 / 7)
          ->setColor($colors[$ii]);
      }
      $bars[] = $bar;
    }

    $bars = phutil_implode_html(
      phutil_tag('br'),
      $bars);

    return $this->wrap(pht('Rainbow Bars'), $bars);
  }

}
