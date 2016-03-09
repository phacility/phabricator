<?php

/**
 * This provides the layout of an AphrontFormView without actually providing
 * the <form /> tag. Useful on its own for creating forms in other forms (like
 * dialogs) or forms which aren't submittable.
 */
final class PHUIFormLayoutView extends AphrontView {

  private $classes = array();
  private $fullWidth;

  public function setFullWidth($width) {
    $this->fullWidth = $width;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function appendInstructions($text) {
    return $this->appendChild(
      phutil_tag(
        'div',
        array(
          'class' => 'aphront-form-instructions',
        ),
        $text));
  }

  public function appendRemarkupInstructions($remarkup) {
    if ($this->getUser() === null) {
      throw new PhutilInvalidStateException('setUser');
    }

    $viewer = $this->getUser();
    $instructions = new PHUIRemarkupView($viewer, $remarkup);

    return $this->appendInstructions($instructions);
  }

  public function render() {
    $classes = $this->classes;
    $classes[] = 'phui-form-view';

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
