<?php

final class PHUIBigInfoView extends AphrontTagView {

  private $icon;
  private $title;
  private $description;
  private $image;
  private $actions = array();

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function addAction(PHUIButtonView $button) {
    $this->actions[] = $button;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-big-info-view';

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-big-info-view-css');

    $icon = null;
    if ($this->icon) {
      $icon = id(new PHUIIconView())
        ->setIcon($this->icon)
        ->addClass('phui-big-info-icon');

      $icon = phutil_tag(
        'div',
        array(
          'class' => 'phui-big-info-icon-container',
        ),
        $icon);
    }

    if ($this->image) {
      $image = phutil_tag(
        'img',
        array(
          'class' => 'phui-big-info-image',
          'src' => $this->image,
        ));
      $icon = phutil_tag(
        'span',
        array(
          'class' => 'phui-big-info-icon-container',
        ),
        $image);
    }

    $title = phutil_tag(
      'div',
      array(
        'class' => 'phui-big-info-title',
      ),
      $this->title);

    $description = phutil_tag(
      'div',
      array(
        'class' => 'phui-big-info-description',
      ),
      $this->description);

    $buttons = array();
    foreach ($this->actions as $button) {
      $buttons[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-big-info-button',
        ),
        $button);
    }

    $actions = null;
    if ($buttons) {
      $actions = phutil_tag(
        'div',
        array(
          'class' => 'phui-big-info-actions',
        ),
        $buttons);
    }

    return array($icon, $title, $description, $actions);

  }

}
