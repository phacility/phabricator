<?php

final class PhabricatorAphrontBarExample extends PhabricatorUIExample {

  public function getName() {
    return "Bars";
  }

  public function getDescription() {
    return 'Like fractions, but more horizontal.';
  }

  public function renderExample() {
    $out = '';
    $out .= $this->renderTestThings('AphrontProgressBarView', 13, 10);
    $out .= $this->renderTestThings('AphrontGlyphBarView', 13, 10);
    $out .= $this->renderWeirdOrderGlyphBars();
    $out .= $this->renderAsciiStarBar();
    return phutil_safe_html($out);
  }

  private function wrap($title, $thing) {
    return id(new AphrontPanelView())
      ->setHeader($title)
      ->appendChild($thing)
      ->render();
  }

  private function renderTestThings($class, $max, $incr) {
    $bars = array();
    for ($ii = 0; $ii <= $max; $ii++) {
      $bars[] = newv($class, array())
        ->setValue($ii * $incr)
        ->setMax($max * $incr)
        ->setCaption("{$ii} outta {$max} ain't bad!");
    }
    return $this->wrap(
      "Test {$class}",
      phutil_implode_html('', mpull($bars, 'render')));
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
        ->setBackgroundGlyph(hsprintf('%s', '____'))
        ->render();
      $views[] = hsprintf('<div style="clear:both;"></div>');
    }

    return $this->wrap(
      "Glyph bars in weird order",
      phutil_implode_html('', $views));
  }

  private function renderAsciiStarBar() {
    return $this->wrap(
      "Ascii star glyph bar",
      id(new AphrontGlyphBarView())
        ->setValue(50)
        ->setMax(100)
        ->setCaption('Glyphs!')
        ->setNumGlyphs(10)
        ->setGlyph(hsprintf('%s', '*'))
        ->render());
  }

}
