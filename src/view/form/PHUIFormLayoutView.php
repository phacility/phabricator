<?php

/**
 * This provides the layout of an AphrontFormView without actually providing
 * the <form /> tag. Useful on its own for creating forms in other forms (like
 * dialogs) or forms which aren't submittable.
 */
final class PHUIFormLayoutView extends AphrontView {

  private $fullWidth;

  public function setFullWidth($width) {
    $this->fullWidth = $width;
    return $this;
  }

  public function render() {
    $classes = array('phui-form-view');

    if ($this->fullWidth) {
      $classes[] = 'phui-form-full-width';
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->renderChildren());

  }
}
