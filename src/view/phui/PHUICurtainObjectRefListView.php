<?php

final class PHUICurtainObjectRefListView
  extends AphrontTagView {

  private $refs = array();
  private $emptyMessage;

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

    if (!$refs) {
      if ($this->emptyMessage) {
        return phutil_tag(
          'div',
          array(
            'class' => 'phui-curtain-object-ref-list-view-empty',
          ),
          $this->emptyMessage);
      }
    }

    return $refs;
  }

  public function newObjectRefView() {
    $ref_view = id(new PHUICurtainObjectRefView())
      ->setViewer($this->getViewer());

    $this->refs[] = $ref_view;

    return $ref_view;
  }

}
