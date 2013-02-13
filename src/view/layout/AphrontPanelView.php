<?php

final class AphrontPanelView extends AphrontView {

  const WIDTH_FULL = 'full';
  const WIDTH_FORM = 'form';
  const WIDTH_WIDE = 'wide';

  private $buttons = array();
  private $header;
  private $caption;
  private $width;
  private $classes = array();
  private $id;

  public function setCreateButton($create_button, $href) {
    $this->addButton(
      phutil_tag(
        'a',
        array(
          'href' => $href,
          'class' => 'button green',
        ),
        $create_button));

    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setCaption($caption) {
    $this->caption = $caption;
    return $this;
  }

  public function setNoBackground() {
    $this->classes[] = 'aphront-panel-plain';
    return $this;
  }

  public function render() {
    if ($this->header !== null) {
      $header = phutil_tag('h1', array(), $this->header);
    } else {
      $header = null;
    }

    if ($this->caption !== null) {
      $caption = phutil_tag(
        'div',
        array('class' => 'aphront-panel-view-caption'),
        $this->caption);
    } else {
      $caption = null;
    }

    $buttons = null;
    if ($this->buttons) {
      $buttons = hsprintf(
        '<div class="aphront-panel-view-buttons">%s</div>',
        phutil_implode_html(" ", $this->buttons));
    }
    $header_elements = hsprintf(
      '<div class="aphront-panel-header">%s%s%s</div>',
      $buttons,
      $header,
      $caption);

    $table = phutil_implode_html('', $this->renderChildren());

    require_celerity_resource('aphront-panel-view-css');

    $classes = $this->classes;
    $classes[] = 'aphront-panel-view';
    if ($this->width) {
      $classes[] = 'aphront-panel-width-'.$this->width;
    }

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
        'id'    => $this->id,
      ),
      array($header_elements, $table));
  }

}
