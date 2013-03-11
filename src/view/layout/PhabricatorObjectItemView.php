<?php

final class PhabricatorObjectItemView extends AphrontView {

  private $header;
  private $href;
  private $attributes = array();
  private $icons = array();
  private $barColor;
  private $object;
  private $effect;
  private $footIcons = array();
  private $handleIcons = array();

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

  public function addFootIcon($icon, $label = null) {
    $this->footIcons[] = array(
      'icon' => $icon,
      'label' => $label,
    );
    return $this;
  }

  public function addHandleIcon(
    PhabricatorObjectHandle $handle,
    $label = null) {
    $this->handleIcons[] = array(
      'icon' => $handle,
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
    $content_classes = array();
    $item_classes = array();
    $content_classes[] = 'phabricator-object-item-content';

    $header = phutil_tag(
      $this->href ? 'a' : 'div',
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
      $item_classes[] = 'phabricator-object-item-with-icons';
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
      $item_classes[] = 'phabricator-object-item-with-attrs';
    }

    $foot = array();

    if ($this->handleIcons) {
      $handle_bar = array();
      foreach ($this->handleIcons as $icon) {
        $handle_bar[] = $this->renderHandleIcon($icon['icon'], $icon['label']);
      }
      $foot[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-handle-icons',
        ),
        $handle_bar);
      $item_classes[] = 'phabricator-object-item-with-handle-icons';
    }

    if ($this->footIcons) {
      $foot_bar = array();
      foreach ($this->footIcons as $icon) {
        $foot_bar[] = $this->renderFootIcon($icon['icon'], $icon['label']);
      }
      $foot[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-foot-icons',
        ),
        $foot_bar);
    }

    if ($foot) {
      $foot = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-foot',
        ),
        $foot);
    }

    $item_classes[] = 'phabricator-object-item';
    if ($this->barColor) {
      $item_classes[] = 'phabricator-object-item-bar-color-'.$this->barColor;
    }

    switch ($this->effect) {
      case 'highlighted':
        $item_classes[] = 'phabricator-object-item-highlighted';
        break;
      case 'selected':
        $item_classes[] = 'phabricator-object-item-selected';
        break;
      case null:
        break;
      default:
        throw new Exception(pht("Invalid effect!"));
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $content_classes),
      ),
      array(
        $header,
        $attrs,
        $this->renderChildren(),
      ));

    return phutil_tag(
      'li',
      array(
        'class' => implode(' ', $item_classes),
      ),
      array(
        $icons,
        $content,
        $foot,
      ));
  }

  private function renderFootIcon($icon, $label) {
    require_celerity_resource('sprite-icon-css');

    $icon = phutil_tag(
      'span',
      array(
        'class' => 'sprite-icon action-'.$icon,
      ),
      '');

    $label = phutil_tag(
      'span',
      array(
      ),
      $label);

    return phutil_tag(
      'span',
      array(
        'class' => 'phabricator-object-item-foot-icon',
      ),
      array($icon, $label));
  }


  private function renderHandleIcon(PhabricatorObjectHandle $handle, $label) {
    Javelin::initBehavior('phabricator-tooltips');

    return javelin_tag(
      'span',
      array(
        'class' => 'phabricator-object-item-handle-icon',
        'sigil' => 'has-tooltip',
        'style' => 'background: url('.$handle->getImageURI().')',
        'meta' => array(
          'tip' => $label,
        ),
      ),
      '');
  }



}
