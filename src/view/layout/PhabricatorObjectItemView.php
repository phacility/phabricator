<?php

final class PhabricatorObjectItemView extends AphrontView {

  private $header;
  private $href;
  private $attributes = array();
  private $details = array();
  private $dates = array();
  private $icons = array();
  private $barColor;
  private $object;
  private $effect;

  public function setEffect($effect) {
    $this->effect = $effect;
    return $this;
  }

  public function getEffect() {
    return $this->effect;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function getHeader() {
    return $this->header;
  }

  public function addIcon($icon, $label = null, $href = null) {
    $this->icons[] = array(
      'icon'  => $icon,
      'label' => $label,
      'href' => $href,
    );
    return $this;
  }

  public function setBarColor($bar_color) {
    $this->barColor = $bar_color;
    return $this;
  }

  public function getBarColor() {
    return $this->barColor;
  }

  public function addAttribute($attribute) {
    $this->attributes[] = $attribute;
    return $this;
  }

  public function render() {
    $header = phutil_tag(
      'a',
      array(
        'href' => $this->href,
        'class' => 'phabricator-object-item-name',
      ),
      $this->header);

    $icons = null;
    if ($this->icons) {
      $icon_list = array();
      foreach ($this->icons as $spec) {
        $icon = $spec['icon'];

        $icon = phutil_tag(
          'span',
          array(
            'class' => 'phabricator-object-item-icon-image '.
                       'sprite-icon action-'.$icon,
          ),
          '');

        $label = phutil_tag(
          'span',
          array(
            'class' => 'phabricator-object-item-icon-label',
          ),
          $spec['label']);


        if ($spec['href']) {
          $icon_href = phutil_tag(
            'a',
            array('href' => $spec['href']),
            array($label, $icon));
        } else {
          $icon_href = array($label, $icon);
        }

        $icon_list[] = phutil_tag(
          'li',
          array(
            'class' => 'phabricator-object-item-icon',
          ),
          $icon_href);
      }

      $icons = phutil_tag(
        'ul',
        array(
          'class' => 'phabricator-object-item-icons',
        ),
        $icon_list);
    }

    $attrs = null;
    if ($this->attributes) {
      $attrs = array();
      $spacer = phutil_tag(
        'span',
        array(
          'class' => 'phabricator-object-item-attribute-spacer',
        ),
        "\xC2\xB7");
      $first = true;
      foreach ($this->attributes as $attribute) {
        $attrs[] = phutil_tag(
          'li',
          array(
            'class' => 'phabricator-object-item-attribute',
          ),
          array(
            ($first ? null : $spacer),
            $attribute,
          ));
        $first = false;
      }
      $attrs = phutil_tag(
        'ul',
        array(
          'class' => 'phabricator-object-item-attributes',
        ),
        $attrs);
    }

    $classes = array();
    $classes[] = 'phabricator-object-item';
    if ($this->barColor) {
      $classes[] = 'phabricator-object-item-bar-color-'.$this->barColor;
    }
    switch ($this->effect) {
      case 'highlighted':
        $classes[] = 'phabricator-object-item-highlighted';
        break;
      case null:
        break;
      default:
        throw new Exception("Invalid effect!");
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-object-item-content',
      ),
      $this->renderSingleView(
        array(
          $header,
          $attrs,
          $this->renderChildren(),
        )));

    return phutil_tag(
      'li',
      array(
        'class' => implode(' ', $classes),
      ),
      array($icons, $content));
  }

}
