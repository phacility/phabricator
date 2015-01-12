<?php

final class AphrontGlyphBarView extends AphrontBarView {

  const BLACK_STAR = "\xE2\x98\x85";
  const WHITE_STAR = "\xE2\x98\x86";

  private $value;
  private $max = 100;
  private $numGlyphs = 5;
  private $fgGlyph;
  private $bgGlyph;

  protected function getDefaultColor() {
    return AphrontBarView::COLOR_AUTO_GOODNESS;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function setMax($max) {
    $this->max = $max;
    return $this;
  }

  public function setNumGlyphs($nn) {
    $this->numGlyphs = $nn;
    return $this;
  }

  public function setGlyph(PhutilSafeHTML $fg_glyph) {
    $this->fgGlyph = $fg_glyph;
    return $this;
  }

  public function setBackgroundGlyph(PhutilSafeHTML $bg_glyph) {
    $this->bgGlyph = $bg_glyph;
    return $this;
  }

  protected function getRatio() {
    return min($this->value, $this->max) / $this->max;
  }

  public function render() {
    require_celerity_resource('aphront-bars');
    $ratio = $this->getRatio();
    $percentage = 100 * $ratio;

    $is_star = false;
    if ($this->fgGlyph) {
      $fg_glyph = $this->fgGlyph;
      if ($this->bgGlyph) {
        $bg_glyph = $this->bgGlyph;
      } else {
        $bg_glyph = $fg_glyph;
      }
    } else {
      $is_star = true;
      $fg_glyph = self::BLACK_STAR;
      $bg_glyph = self::WHITE_STAR;
    }

    $fg_glyphs = array_fill(0, $this->numGlyphs, $fg_glyph);
    $bg_glyphs = array_fill(0, $this->numGlyphs, $bg_glyph);

    $color = $this->getColor();

    return phutil_tag(
      'div',
      array(
        'class' => "aphront-bar glyph color-{$color}",
      ),
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'glyphs'.($is_star ? ' starstar' : ''),
          ),
          array(
            phutil_tag(
              'div',
              array(
                'class' => 'fg',
                'style' => "width: {$percentage}%;",
              ),
              $fg_glyphs),
            phutil_tag(
              'div',
              array(),
              $bg_glyphs),
          )),
        phutil_tag(
          'div',
          array('class' => 'caption'),
          $this->getCaption()),
      ));
  }

}
