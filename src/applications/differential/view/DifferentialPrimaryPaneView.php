<?php

final class DifferentialPrimaryPaneView
  extends DifferentialCodeWidthSensitiveView {

  private $id;

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function render() {

    // This is chosen somewhat arbitrarily so the math works out correctly
    // for 80 columns and sets it to the preexisting width (1162px). It may
    // need some tweaking, but when lineWidth = 80, the computed pixel width
    // should be 1162px or something along those lines.

    // Override the 'td' width rule with a more specific, inline style tag.
    // TODO: move this to <head> somehow.
    $td_width = ceil((88 / 80) * $this->getLineWidth());
    $style_tag = phutil_render_tag(
      'style',
      array(
        'type' => 'text/css',
      ),
      ".differential-diff td { width: {$td_width}ex; }");

    return phutil_render_tag(
      'div',
      array(
        'class' => 'differential-primary-pane',
        'id'    => $this->id,
      ),
      $style_tag.$this->renderChildren());
  }

}
