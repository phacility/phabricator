<?php

final class AphrontNoteView extends AphrontView {

  private $title;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function render() {
    $title = phutil_tag(
      'div',
      array(
        'class' => 'title',
      ),
      $this->title);

    $inner = phutil_tag(
      'div',
      array(
        'class' => 'inner',
      ),
      $this->renderChildren());

    require_celerity_resource('aphront-notes');
    return phutil_tag(
      'div',
      array(
        'class' => 'aphront-note',
      ),
      array(
        $title,
        $inner));
  }

}
