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

  public function addIcon($icon, $label = null) {
    $this->icons[] = array(
      'icon'  => $icon,
      'label' => $label,
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
    $header = phutil_render_tag(
      'a',
      array(
        'href' => $this->href,
        'class' => 'phabricator-object-item-name',
      ),
      phutil_escape_html($this->header));

    $icons = null;
    if ($this->icons) {
      $icon_list = array();
      foreach ($this->icons as $spec) {
        $icon = $spec['icon'];

        $icon = phutil_render_tag(
          'span',
          array(
            'class' => 'phabricator-object-item-icon-image '.
                       'sprite-icon action-'.$icon,
          ),
          '');

        $label = phutil_render_tag(
          'span',
          array(
            'class' => 'phabricator-object-item-icon-label',
          ),
          phutil_escape_html($spec['label']));

        $icon_list[] = phutil_render_tag(
          'li',
          array(
            'class' => 'phabricator-object-item-icon',
          ),
          $label.$icon);
      }

      $icons = phutil_render_tag(
        'ul',
        array(
          'class' => 'phabricator-object-item-icons',
        ),
        implode('', $icon_list));
    }

    $attrs = null;
    if ($this->attributes) {
      $attrs = array();
      $spacer = phutil_render_tag(
        'span',
        array(
          'class' => 'phabricator-object-item-attribute-spacer',
        ),
        '&middot;');
      $first = true;
      foreach ($this->attributes as $attribute) {
        $attrs[] = phutil_render_tag(
          'li',
          array(
            'class' => 'phabricator-object-item-attribute',
          ),
          ($first ? null : $spacer).$attribute);
        $first = false;
      }
      $attrs = phutil_render_tag(
        'ul',
        array(
          'class' => 'phabricator-object-item-attributes',
        ),
        implode('', $attrs));
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

    $content = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-object-item-content',
      ),
      $header.$attrs.$this->renderChildren());

    return phutil_render_tag(
      'li',
      array(
        'class' => implode(' ', $classes),
      ),
      $icons.$content);
  }

}
