<?php

final class PHUITagView extends AphrontTagView {

  const TYPE_PERSON         = 'person';
  const TYPE_OBJECT         = 'object';
  const TYPE_STATE          = 'state';
  const TYPE_SHADE          = 'shade';
  const TYPE_OUTLINE        = 'outline';

  const COLOR_RED           = 'red';
  const COLOR_ORANGE        = 'orange';
  const COLOR_YELLOW        = 'yellow';
  const COLOR_BLUE          = 'blue';
  const COLOR_INDIGO        = 'indigo';
  const COLOR_VIOLET        = 'violet';
  const COLOR_GREEN         = 'green';
  const COLOR_BLACK         = 'black';
  const COLOR_SKY           = 'sky';
  const COLOR_FIRE          = 'fire';
  const COLOR_GREY          = 'grey';
  const COLOR_WHITE         = 'white';
  const COLOR_PINK          = 'pink';
  const COLOR_BLUEGREY      = 'bluegrey';
  const COLOR_CHECKERED     = 'checkered';
  const COLOR_DISABLED      = 'disabled';
  const COLOR_PLACEHOLDER = 'placeholder';

  const COLOR_OBJECT        = 'object';
  const COLOR_PERSON        = 'person';

  const BORDER_NONE         = 'border-none';

  private $type;
  private $href;
  private $name;
  private $phid;
  private $color;
  private $backgroundColor;
  private $dotColor;
  private $closed;
  private $external;
  private $icon;
  private $shade;
  private $slimShady;
  private $border;
  private $contextObject;
  private $isExiled;

  public function setType($type) {
    $this->type = $type;
    switch ($type) {
      case self::TYPE_SHADE:
      case self::TYPE_OUTLINE:
        break;
      case self::TYPE_OBJECT:
        $this->setBackgroundColor(self::COLOR_OBJECT);
        break;
      case self::TYPE_PERSON:
        $this->setBackgroundColor(self::COLOR_PERSON);
        break;
    }
    return $this;
  }

  /**
   * This method has been deprecated, use @{method:setColor} instead.
   *
   * @deprecated
   */
  public function setShade($shade) {
    phlog(
      pht('Deprecated call to setShade(), use setColor() instead.'));
    $this->color = $shade;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function setDotColor($dot_color) {
    $this->dotColor = $dot_color;
    return $this;
  }

  public function setBackgroundColor($background_color) {
    $this->backgroundColor = $background_color;
    return $this;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setClosed($closed) {
    $this->closed = $closed;
    return $this;
  }

  public function setBorder($border) {
    $this->border = $border;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setSlimShady($is_eminem) {
    $this->slimShady = $is_eminem;
    return $this;
  }

  protected function getTagName() {
    return ($this->href !== null && strlen($this->href)) ? 'a' : 'span';
  }

  public function setContextObject($context_object) {
    $this->contextObject = $context_object;
    return $this;
  }

  public function getContextObject() {
    return $this->contextObject;
  }

  public function setIsExiled($is_exiled) {
    $this->isExiled = $is_exiled;
    return $this;
  }

  public function getIsExiled() {
    return $this->isExiled;
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-tag-view-css');

    $classes = array(
      'phui-tag-view',
      'phui-tag-type-'.$this->type,
    );

    if ($this->color) {
      $classes[] = 'phui-tag-'.$this->color;
    }

    if ($this->slimShady) {
      $classes[] = 'phui-tag-slim';
    }

    if ($this->type == self::TYPE_SHADE) {
      $classes[] = 'phui-tag-shade';
    }

    if ($this->icon) {
      $classes[] = 'phui-tag-icon-view';
    }

    if ($this->border) {
      $classes[] = 'phui-tag-'.$this->border;
    }

    if ($this->getIsExiled()) {
      $classes[] = 'phui-tag-exiled';
    }

    $attributes = array(
      'href' => $this->href,
      'class' => $classes,
    );

    if ($this->external) {
      $attributes += array(
        'target' => '_blank',
        'rel' => 'noreferrer',
      );
    }

    if ($this->phid) {
      Javelin::initBehavior('phui-hovercards');

      $hovercard_spec = array(
        'objectPHID' => $this->phid,
      );

      $context_object = $this->getContextObject();
      if ($context_object) {
        $hovercard_spec['contextPHID'] = $context_object->getPHID();
      }

      $attributes += array(
        'sigil' => 'hovercard',
        'meta' => array(
          'hovercardSpec' => $hovercard_spec,
        ),
      );
    }

    return $attributes;
  }

  protected function getTagContent() {
    if (!$this->type) {
      throw new PhutilInvalidStateException('setType', 'render');
    }

    $color = null;
    if (!$this->shade && $this->backgroundColor) {
      $color = 'phui-tag-color-'.$this->backgroundColor;
    }

    if ($this->dotColor) {
      $dotcolor = 'phui-tag-color-'.$this->dotColor;
      $dot = phutil_tag(
        'span',
        array(
          'class' => 'phui-tag-dot '.$dotcolor,
        ),
        '');
    } else {
      $dot = null;
    }

    if ($this->icon) {
      $icon = id(new PHUIIconView())
        ->setIcon($this->icon);
    } else {
      $icon = null;
    }

    $content = phutil_tag(
      'span',
      array(
        'class' => 'phui-tag-core '.$color,
      ),
      array($dot, $icon, $this->name));

    if ($this->closed) {
      $content = phutil_tag(
        'span',
        array(
          'class' => 'phui-tag-core-closed',
        ),
        array($icon, $content));
    }

    return $content;
  }

  public static function getTagTypes() {
    return array(
      self::TYPE_PERSON,
      self::TYPE_OBJECT,
      self::TYPE_STATE,
    );
  }

  public static function getColors() {
    return array(
      self::COLOR_RED,
      self::COLOR_ORANGE,
      self::COLOR_YELLOW,
      self::COLOR_BLUE,
      self::COLOR_INDIGO,
      self::COLOR_VIOLET,
      self::COLOR_GREEN,
      self::COLOR_BLACK,
      self::COLOR_GREY,
      self::COLOR_WHITE,

      self::COLOR_OBJECT,
      self::COLOR_PERSON,
    );
  }

  public static function getShades() {
    return array_keys(self::getShadeMap());
  }

  public static function getShadeMap() {
    return array(
      self::COLOR_RED => pht('Red'),
      self::COLOR_ORANGE => pht('Orange'),
      self::COLOR_YELLOW => pht('Yellow'),
      self::COLOR_BLUE => pht('Blue'),
      self::COLOR_INDIGO => pht('Indigo'),
      self::COLOR_VIOLET => pht('Violet'),
      self::COLOR_GREEN => pht('Green'),
      self::COLOR_GREY => pht('Grey'),
      self::COLOR_PINK => pht('Pink'),
      self::COLOR_CHECKERED => pht('Checkered'),
      self::COLOR_DISABLED => pht('Disabled'),
    );
  }

  public static function getShadeName($shade) {
    return idx(self::getShadeMap(), $shade, $shade);
  }

  public static function getOutlines() {
    return array_keys(self::getOutlineMap());
  }

  public static function getOutlineMap() {
    return array(
      self::COLOR_RED => pht('Red'),
      self::COLOR_ORANGE => pht('Orange'),
      self::COLOR_YELLOW => pht('Yellow'),
      self::COLOR_BLUE => pht('Blue'),
      self::COLOR_INDIGO => pht('Indigo'),
      self::COLOR_VIOLET => pht('Violet'),
      self::COLOR_GREEN => pht('Green'),
      self::COLOR_GREY => pht('Grey'),
      self::COLOR_PINK => pht('Pink'),
      self::COLOR_SKY => pht('Sky'),
      self::COLOR_FIRE => pht('Fire'),
      self::COLOR_BLACK => pht('Black'),
      self::COLOR_DISABLED => pht('Disabled'),
    );
  }

  public static function getOutlineName($outline) {
    return idx(self::getOutlineMap(), $outline, $outline);
  }


  public function setExternal($external) {
    $this->external = $external;
    return $this;
  }

  public function getExternal() {
    return $this->external;
  }

}
