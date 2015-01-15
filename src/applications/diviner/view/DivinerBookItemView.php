<?php

final class DivinerBookItemView extends AphrontTagView {

  private $title;
  private $subtitle;
  private $type;
  private $href;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setSubtitle($subtitle) {
    $this->subtitle = $subtitle;
    return $this;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  protected function getTagName() {
    return 'a';
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'diviner-book-item',
      'href' => $this->href,
    );
  }

  protected function getTagContent() {
    require_celerity_resource('diviner-shared-css');

    $title = phutil_tag(
      'span',
        array(
          'class' => 'diviner-book-item-title',
        ),
      $this->title);

    $subtitle = phutil_tag(
      'span',
        array(
          'class' => 'diviner-book-item-subtitle',
        ),
      $this->subtitle);

    $type = phutil_tag(
      'span',
        array(
          'class' => 'diviner-book-item-type',
        ),
      $this->type);

    return array($title, $type, $subtitle);
  }

}
