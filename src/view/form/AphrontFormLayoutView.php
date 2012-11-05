<?php

/**
 * This provides the layout of an AphrontFormView without actually providing
 * the <form /> tag. Useful on its own for creating forms in other forms (like
 * dialogs) or forms which aren't submittable.
 */
final class AphrontFormLayoutView extends AphrontView {

  private $backgroundShading;
  private $padded;

  public function setBackgroundShading($shading) {
    $this->backgroundShading = $shading;
    return $this;
  }

  public function setPadded($padded) {
    $this->padded = $padded;
    return $this;
  }

  public function render() {
    $classes = array('aphront-form-view');

    if ($this->backgroundShading) {
      $classes[] = 'aphront-form-view-shaded';
    }

    if ($this->padded) {
      $classes[] = 'aphront-form-view-padded';
    }

    $classes = implode(' ', $classes);

    return phutil_render_tag(
      'div',
      array(
        'class' => $classes,
      ),
      $this->renderChildren());
  }
}
