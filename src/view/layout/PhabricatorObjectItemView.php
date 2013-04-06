<?php

final class PhabricatorObjectItemView extends AphrontTagView {

  private $objectName;
  private $header;
  private $href;
  private $attributes = array();
  private $icons = array();
  private $barColor;
  private $object;
  private $effect;
  private $footIcons = array();
  private $handleIcons = array();
  private $bylines = array();
  private $grippable;

  public function setObjectName($name) {
    $this->objectName = $name;
    return $this;
  }

  public function setGrippable($grippable) {
    $this->grippable = $grippable;
    return $this;
  }

  public function getGrippable() {
    return $this->grippable;
  }

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

  public function addByline($byline) {
    $this->bylines[] = $byline;
    return $this;
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

  protected function getTagName() {
    return 'li';
  }

  protected function getTagAttributes() {
    $item_classes = array();
    $item_classes[] = 'phabricator-object-item';

    if ($this->icons) {
      $item_classes[] = 'phabricator-object-item-with-icons';
    }

    if ($this->attributes) {
      $item_classes[] = 'phabricator-object-item-with-attrs';
    }

    if ($this->handleIcons) {
      $item_classes[] = 'phabricator-object-item-with-handle-icons';
    }

    if ($this->barColor) {
      $item_classes[] = 'phabricator-object-item-bar-color-'.$this->barColor;
    }

    if ($this->footIcons) {
      $item_classes[] = 'phabricator-object-item-with-foot-icons';
    }

    if ($this->bylines) {
      $item_classes[] = 'phabricator-object-item-with-bylines';
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

    if ($this->getGrippable()) {
      $item_classes[] = 'phabricator-object-item-grippable';
    }

    return array(
      'class' => $item_classes,
    );
  }

  public function getTagContent() {
    $content_classes = array();
    $content_classes[] = 'phabricator-object-item-content';

    $header_name = null;
    if ($this->objectName) {
      $header_name = array(
        phutil_tag(
          'span',
          array(
            'class' => 'phabricator-object-item-objname',
          ),
          $this->objectName),
        ' ',
      );
    }

    $header_link = phutil_tag(
      $this->href ? 'a' : 'div',
      array(
        'href' => $this->href,
        'class' => 'phabricator-object-item-link',
      ),
      $this->header);

    $header = javelin_tag(
      'div',
      array(
        'class' => 'phabricator-object-item-name',
        'sigil' => 'slippery',
      ),
      array(
        $header_name,
        $header_link,
      ));

    $icons = array();
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

        $classes = array();
        $classes[] = 'phabricator-object-item-icon';
        if ($spec['icon'] == 'none') {
          $classes[] = 'phabricator-object-item-icon-none';
        }

        $icon_list[] = phutil_tag(
          'li',
          array(
            'class' => implode(' ', $classes),
          ),
          $icon_href);
      }

      $icons[] = phutil_tag(
        'ul',
        array(
          'class' => 'phabricator-object-item-icons',
        ),
        $icon_list);
    }

    if ($this->handleIcons) {
      $handle_bar = array();
      foreach ($this->handleIcons as $icon) {
        $handle_bar[] = $this->renderHandleIcon($icon['icon'], $icon['label']);
      }
      $icons[] = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-handle-icons',
        ),
        $handle_bar);
    }

    $bylines = array();
    if ($this->bylines) {
      foreach ($this->bylines as $byline) {
        $bylines[] = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-object-item-byline',
          ),
          $byline);
      }
      $bylines = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-bylines',
        ),
        $bylines);
    }

    if ($icons) {
      $icons = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-icon-pane',
        ),
        $icons);
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

    $foot = null;
    if ($this->footIcons) {
      $foot_bar = array();
      foreach ($this->footIcons as $icon) {
        $foot_bar[] = $this->renderFootIcon($icon['icon'], $icon['label']);
      }
      $foot = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-foot-icons',
        ),
        $foot_bar);
    }

    $grippable = null;
    if ($this->getGrippable()) {
      $grippable = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-object-item-grip',
        ),
        '');
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $content_classes),
      ),
      array(
        $attrs,
        $this->renderChildren(),
        $foot,
      ));

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-object-item-frame',
      ),
      array(
        $grippable,
        $header,
        $icons,
        $bylines,
        $content,
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

    $options = array(
      'class' => 'phabricator-object-item-handle-icon',
      'style' => 'background-image: url('.$handle->getImageURI().')',
    );

    if (strlen($label)) {
      $options['sigil'] = 'has-tooltip';
      $options['meta']  = array('tip' => $label);
    }

    return javelin_tag(
      'span',
      $options,
      '');
  }



}
