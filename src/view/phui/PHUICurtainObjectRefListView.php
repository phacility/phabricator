<?php

final class PHUICurtainObjectRefListView
  extends AphrontTagView {

  private $refs = array();
  private $emptyMessage;
  private $tail = array();

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-curtain-object-ref-list-view',
    );
  }

  public function setEmptyMessage($empty_message) {
    $this->emptyMessage = $empty_message;
    return $this;
  }

  protected function getTagContent() {
    $refs = $this->refs;

    if (!$refs && ($this->emptyMessage !== null)) {
      $view = phutil_tag(
        'div',
        array(
          'class' => 'phui-curtain-object-ref-list-view-empty',
        ),
        $this->emptyMessage);
    } else {
      $view = $refs;
    }

    $tail = null;
    if ($this->tail) {
      $tail = phutil_tag(
        'div',
        array(
          'class' => 'phui-curtain-object-ref-list-view-tail',
        ),
        $this->tail);
    }

    return array(
      $view,
      $tail,
    );
  }

  public function newObjectRefView() {
    $ref_view = id(new PHUICurtainObjectRefView())
      ->setViewer($this->getViewer());

    $this->refs[] = $ref_view;

    return $ref_view;
  }

  public function newTailLink() {
    $link = new PHUILinkView();

    $this->tail[] = $link;

    return $link;
  }

}
