<?php

final class ConpherenceMenuItemView extends AphrontTagView {

  private $title;
  private $subtitle;
  private $imageURI;
  private $href;
  private $epoch;
  private $unreadCount;

  public function setUnreadCount($unread_count) {
    $this->unreadCount = $unread_count;
    return $this;
  }
  public function getUnreadCount() {
    return $this->unreadCount;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }
  public function getEpoch() {
    return $this->epoch;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }
  public function getHref() {
    return $this->href;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }
  public function getImageURI() {
    return $this->imageURI;
  }

  public function setSubtitle($subtitle) {
    $this->subtitle = $subtitle;
    return $this;
  }
  public function getSubtitle() {
    return $this->subtitle;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  protected function getTagName() {
    return 'a';
  }

  protected function getTagAttributes() {
    $classes = array('conpherence-menu-item-view');
    return array(
      'class' => $classes,
      'href' => $this->href,
    );
  }

  protected function getTagContent() {
    $image = null;
    if ($this->imageURI) {
      $image = phutil_tag(
        'span',
        array(
          'class' => 'conpherence-menu-item-image',
          'style' => 'background-image: url('.$this->imageURI.');',
        ),
        '');
    }
    $title = null;
    if ($this->title) {
      $title = phutil_tag(
        'span',
        array(
          'class' => 'conpherence-menu-item-title',
        ),
        $this->title);
    }
    $subtitle = null;
    if ($this->subtitle) {
      $subtitle = phutil_tag(
        'span',
        array(
          'class' => 'conpherence-menu-item-subtitle',
        ),
        $this->subtitle);
    }
    $unread_count = null;
    if ($this->unreadCount) {
      $unread_count = phutil_tag(
        'span',
        array(
          'class' => 'conpherence-menu-item-unread-count',
        ),
        (int)$this->unreadCount);
    }

    return array(
      $image,
      $title,
      $subtitle,
      $unread_count,
    );
  }

}
