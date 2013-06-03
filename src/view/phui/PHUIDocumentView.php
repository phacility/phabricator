<?php

final class PHUIDocumentView extends AphrontTagView {

  private $offset;

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function getTagAttributes() {
    $classes = array();

    if ($this->offset) {
      $classes[] = 'phui-document-offset';
    };

    return array(
      'class' => $classes,
    );
  }

  public function getTagContent() {
    require_celerity_resource('phui-document-view-css');

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-document-view',
      ),
      phutil_tag(
        'div',
        array(
          'class' => 'phui-document-content',
        ),
        $this->renderChildren()));
  }

}
