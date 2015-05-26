<?php

final class PHUIButtonView extends AphrontTagView {

  const GREEN = 'green';
  const GREY = 'grey';
  const DISABLED = 'disabled';

  const SIMPLE = 'simple';
  const SIMPLE_YELLOW = 'simple simple-yellow';
  const SIMPLE_GREY = 'simple simple-grey';
  const SIMPLE_BLUE = 'simple simple-blue';

  const SMALL = 'small';
  const BIG = 'big';

  private $size;
  private $text;
  private $subtext;
  private $color;
  private $tag = 'button';
  private $dropdown;
  private $icon;
  private $iconFont;
  private $href = null;
  private $title = null;
  private $disabled;
  private $name;
  private $tooltip;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setSubtext($subtext) {
    $this->subtext = $subtext;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function setTag($tag) {
    $this->tag = $tag;
    return $this;
  }

  public function setSize($size) {
    $this->size = $size;
    return $this;
  }

  public function setDropdown($dd) {
    $this->dropdown = $dd;
    return $this;
  }

  public function setTooltip($text) {
    $this->tooltip = $text;
    return $this;
  }

  public function setIcon(PHUIIconView $icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setIconFont($icon) {
    $icon = id(new PHUIIconView())
      ->setIconFont($icon);
    $this->setIcon($icon);
    return $this;
  }

  protected function getTagName() {
    return $this->tag;
  }

  public function setDropdownMenu(PhabricatorActionListView $actions) {
    Javelin::initBehavior('phui-dropdown-menu');

    $this->addSigil('phui-dropdown-menu');
    $this->setMetadata(
      array(
        'items' => $actions,
      ));

    return $this;
  }

  protected function getTagAttributes() {

    require_celerity_resource('phui-button-css');

    $classes = array();
    $classes[] = 'button';

    if ($this->color) {
      $classes[] = $this->color;
    }

    if ($this->size) {
      $classes[] = $this->size;
    }

    if ($this->dropdown) {
      $classes[] = 'dropdown';
    }

    if ($this->icon) {
      $classes[] = 'has-icon';
    }

    if ($this->disabled) {
      $classes[] = 'disabled';
    }

    $sigil = null;
    $meta = null;
    if ($this->tooltip) {
      Javelin::initBehavior('phabricator-tooltips');
      require_celerity_resource('aphront-tooltip-css');
      $sigil = 'has-tooltip';
      $meta = array(
        'tip' => $this->tooltip,
      );
    }

    return array(
      'class'  => $classes,
      'href'   => $this->href,
      'name'   => $this->name,
      'title'  => $this->title,
      'sigil'  => $sigil,
      'meta'   => $meta,
    );
  }

  protected function getTagContent() {

    $icon = null;
    $text = $this->text;
    if ($this->icon) {
      $icon = $this->icon;

      $subtext = null;
      if ($this->subtext) {
        $subtext = phutil_tag(
          'div', array('class' => 'phui-button-subtext'), $this->subtext);
      }
      $text = phutil_tag(
        'div', array('class' => 'phui-button-text'), array($text, $subtext));
    }

    $caret = null;
    if ($this->dropdown) {
      $caret = phutil_tag('span', array('class' => 'caret'), '');
    }

    return array($icon, $text, $caret);
  }
}
