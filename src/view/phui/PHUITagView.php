<?php

final class PHUITagView extends AphrontTagView {

  const TYPE_PERSON         = 'person';
  const TYPE_OBJECT         = 'object';
  const TYPE_STATE          = 'state';
  const TYPE_SHADE          = 'shade';

  const COLOR_RED           = 'red';
  const COLOR_ORANGE        = 'orange';
  const COLOR_YELLOW        = 'yellow';
  const COLOR_BLUE          = 'blue';
  const COLOR_INDIGO        = 'indigo';
  const COLOR_VIOLET        = 'violet';
  const COLOR_GREEN         = 'green';
  const COLOR_BLACK         = 'black';
  const COLOR_GREY          = 'grey';
  const COLOR_WHITE         = 'white';
  const COLOR_PINK          = 'pink';
  const COLOR_BLUEGREY      = 'bluegrey';
  const COLOR_CHECKERED     = 'checkered';
  const COLOR_DISABLED      = 'disabled';

  const COLOR_OBJECT        = 'object';
  const COLOR_PERSON        = 'person';

  private $type;
  private $href;
  private $name;
  private $phid;
  private $backgroundColor;
  private $dotColor;
  private $closed;
  private $external;
  private $icon;
  private $shade;
  private $slimShady;

  public function setType($type) {
    $this->type = $type;
    switch ($type) {
      case self::TYPE_SHADE:
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

  public function setShade($shade) {
    $this->shade = $shade;
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

  public function setClosed($closed) {
    $this->closed = $closed;
    return $this;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setSlimShady($mm) {
    $this->slimShady = $mm;
    return $this;
  }

  protected function getTagName() {
    return strlen($this->href) ? 'a' : 'span';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-tag-view-css');

    $classes = array(
      'phui-tag-view',
      'phui-tag-type-'.$this->type,
    );

    if ($this->shade) {
      $classes[] = 'phui-tag-shade';
      $classes[] = 'phui-tag-shade-'.$this->shade;
      if ($this->slimShady) {
        $classes[] = 'phui-tag-shade-slim';
      }
    }

    if ($this->icon) {
      $classes[] = 'phui-tag-icon-view';
    }

    if ($this->phid) {
      Javelin::initBehavior('phabricator-hovercards');

      $attributes = array(
        'href'  => $this->href,
        'sigil' => 'hovercard',
        'meta'  => array(
          'hoverPHID' => $this->phid,
        ),
        'target' => $this->external ? '_blank' : null,
      );
    } else {
      $attributes = array(
        'href'  => $this->href,
        'target' => $this->external ? '_blank' : null,
      );
    }

    return $attributes + array('class' => $classes);
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
        ->setIconFont($this->icon);
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


  public function setExternal($external) {
    $this->external = $external;
    return $this;
  }

  public function getExternal() {
    return $this->external;
  }

}
