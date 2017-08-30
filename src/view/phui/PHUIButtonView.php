<?php

final class PHUIButtonView extends AphrontTagView {

  const GREEN = 'green';
  const GREY = 'grey';
  const BLUE = 'blue';
  const RED = 'red';
  const DISABLED = 'disabled';

  const SMALL = 'small';
  const BIG = 'big';

  const BUTTONTYPE_DEFAULT = 'buttontype.default';
  const BUTTONTYPE_SIMPLE = 'buttontype.simple';

  private $size;
  private $text;
  private $subtext;
  private $color;
  private $tag = 'button';
  private $dropdown;
  private $icon;
  private $iconFirst;
  private $href = null;
  private $title = null;
  private $disabled;
  private $selected;
  private $name;
  private $tooltip;
  private $noCSS;
  private $hasCaret;
  private $buttonType = self::BUTTONTYPE_DEFAULT;

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

  public function getColor() {
    return $this->color;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function setSelected($selected) {
    $this->selected = $selected;
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

  public function setNoCSS($no_css) {
    $this->noCSS = $no_css;
    return $this;
  }

  public function setHasCaret($has_caret) {
    $this->hasCaret = $has_caret;
    return $this;
  }

  public function getHasCaret() {
    return $this->hasCaret;
  }

  public function setButtonType($button_type) {
    $this->buttonType = $button_type;
    return $this;
  }

  public function getButtonType() {
    return $this->buttonType;
  }

  public function setIcon($icon, $first = true) {
    if (!($icon instanceof PHUIIconView)) {
      $icon = id(new PHUIIconView())
        ->setIcon($icon);
    }
    $this->icon = $icon;
    $this->iconFirst = $first;
    return $this;
  }

  protected function getTagName() {
    return $this->tag;
  }

  public function setDropdownMenu(PhabricatorActionListView $actions) {
    Javelin::initBehavior('phui-dropdown-menu');

    $this->addSigil('phui-dropdown-menu');
    $this->setDropdown(true);
    $this->setMetadata($actions->getDropdownMenuMetadata());

    return $this;
  }

  public function setDropdownMenuID($id) {
    Javelin::initBehavior('phui-dropdown-menu');

    $this->addSigil('phui-dropdown-menu');
    $this->setMetadata(
      array(
        'menuID' => $id,
      ));

    return $this;
  }

  protected function getTagAttributes() {

    require_celerity_resource('phui-button-css');
    require_celerity_resource('phui-button-simple-css');

    $classes = array();
    $classes[] = 'button';

    if ($this->color) {
      $classes[] = 'button-'.$this->color;
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

    if ($this->text !== null) {
      $classes[] = 'has-text';
    }

    if ($this->iconFirst == false) {
      $classes[] = 'icon-last';
    }

    if ($this->disabled) {
      $classes[] = 'disabled';
    }

    if ($this->selected) {
      $classes[] = 'selected';
    }

    switch ($this->getButtonType()) {
      case self::BUTTONTYPE_DEFAULT:
        $classes[] = 'phui-button-default';
        break;
      case self::BUTTONTYPE_SIMPLE:
        $classes[] = 'phui-button-simple';
        break;
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

    if ($this->noCSS) {
      $classes = array();
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

    $icon = $this->icon;
    $text = null;
    $subtext = null;

    if ($this->subtext) {
      $subtext = phutil_tag(
        'div',
        array(
          'class' => 'phui-button-subtext',
        ),
      $this->subtext);
    }

    if ($this->text !== null) {
      $text = phutil_tag(
        'div',
        array(
          'class' => 'phui-button-text',
        ),
        array(
          $this->text,
          $subtext,
        ));
    }

    $caret = null;
    if ($this->dropdown || $this->getHasCaret()) {
      $caret = phutil_tag('span', array('class' => 'caret'), '');
    }

    if ($this->iconFirst == true) {
      return array($icon, $text, $caret);
    } else {
      return array($text, $icon, $caret);
    }
  }
}
