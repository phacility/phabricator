<?php

final class PhabricatorAphrontBarUIExample extends PhabricatorUIExample {

  public function getName() {
    return 'Bars';
  }

  public function getDescription() {
    return 'Like fractions, but more horizontal.';
  }

  public function renderExample() {
    $out = array();
    $out[] = $this->renderTestThings('AphrontProgressBarView', 13, 10);
    $out[] = $this->renderTestThings('AphrontGlyphBarView', 13, 10);
    $out[] = $this->renderWeirdOrderGlyphBars();
    $out[] = $this->renderAsciiStarBar();
    return $out;
  }

  private function wrap($title, $thing) {
    $thing = phutil_tag_div('ml grouped', $thing);
    return id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($thing);
  }

  private function renderTestThings($class, $max, $incr) {
    $bars = array();
    for ($ii = 0; $ii <= $max; $ii++) {
      $bars[] = newv($class, array())
        ->setValue($ii * $incr)
        ->setMax($max * $incr)
        ->setCaption("{$ii} outta {$max} ain't bad!");
    }
    return $this->wrap("Test {$class}", $bars);
  }

  private function renderWeirdOrderGlyphBars() {
    $views = array();
    $indices = array(1, 3, 7, 4, 2, 8, 9, 5, 10, 6);
    $max = count($indices);
    foreach ($indices as $index) {
      $views[] = id(new AphrontGlyphBarView())
        ->setValue($index)
        ->setMax($max)
        ->setNumGlyphs(5)
        ->setCaption("Lol score is {$index}/{$max}")
        ->setGlyph(hsprintf('%s', 'LOL!'))
        ->setBackgroundGlyph(hsprintf('%s', '____'));
      $views[] = hsprintf('<div style="clear:both;"></div>');
    }

    return $this->wrap(
      'Glyph bars in weird order',
      $views);
  }

  private function renderAsciiStarBar() {
    $bar = id(new AphrontGlyphBarView())
        ->setValue(50)
        ->setMax(100)
        ->setCaption('Glyphs!')
        ->setNumGlyphs(10)
        ->setGlyph(hsprintf('%s', '*'));

    return $this->wrap(
      'Ascii star glyph bar', $bar);
  }

}
