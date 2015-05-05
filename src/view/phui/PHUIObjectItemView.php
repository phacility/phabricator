<?php

final class PHUIObjectItemView extends AphrontTagView {

  private $objectName;
  private $header;
  private $subhead;
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
  private $actions = array();
  private $headIcons = array();
  private $disabled;
  private $imageURI;
  private $state;
  private $fontIcon;
  private $imageIcon;
  private $titleText;

  const AGE_FRESH = 'fresh';
  const AGE_STALE = 'stale';
  const AGE_OLD   = 'old';

  const STATE_SUCCESS = 'green';
  const STATE_FAIL = 'red';
  const STATE_WARN = 'yellow';
  const STATE_NOTE = 'blue';
  const STATE_BUILD = 'sky';

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function addHeadIcon($icon) {
    $this->headIcons[] = $icon;
    return $this;
  }

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

  public function setSubHead($subhead) {
    $this->subhead = $subhead;
    return $this;
  }

  public function setTitleText($title_text) {
    $this->titleText = $title_text;
    return $this;
  }

  public function getTitleText() {
    return $this->titleText;
  }

  public function getHeader() {
    return $this->header;
  }

  public function addByline($byline) {
    $this->bylines[] = $byline;
    return $this;
  }

  public function setImageURI($image_uri) {
    $this->imageURI = $image_uri;
    return $this;
  }

  public function getImageURI() {
    return $this->imageURI;
  }

  public function setImageIcon($image_icon) {
    $this->imageIcon = $image_icon;
    return $this;
  }

  public function getImageIcon() {
    return $this->imageIcon;
  }

  public function setState($state) {
    $this->state = $state;
    switch ($state) {
      case self::STATE_SUCCESS:
        $fi = 'fa-check-circle green';
      break;
      case self::STATE_FAIL:
        $fi = 'fa-times-circle red';
      break;
      case self::STATE_WARN:
        $fi = 'fa-exclamation-circle yellow';
      break;
      case self::STATE_NOTE:
        $fi = 'fa-info-circle blue';
      break;
      case self::STATE_BUILD:
        $fi = 'fa-refresh ph-spin sky';
      break;
    }
    $this->setFontIcon($fi);
    return $this;
  }

  public function setFontIcon($icon) {
    $this->fontIcon = id(new PHUIIconView())
      ->setIconFont($icon);
    return $this;
  }

  public function setEpoch($epoch, $age = self::AGE_FRESH) {
    $date = phabricator_datetime($epoch, $this->getUser());

    $days = floor((time() - $epoch) / 60 / 60 / 24);

    switch ($age) {
      case self::AGE_FRESH:
        $this->addIcon('none', $date);
        break;
      case self::AGE_STALE:
        $attr = array(
          'tip' => pht('Stale (%s day(s))', new PhutilNumber($days)),
          'class' => 'icon-age-stale',
        );

        $this->addIcon('fa-clock-o yellow', $date, $attr);
        break;
      case self::AGE_OLD:
        $attr = array(
          'tip' =>  pht('Old (%s day(s))', new PhutilNumber($days)),
          'class' => 'icon-age-old',
        );
        $this->addIcon('fa-clock-o red', $date, $attr);
        break;
      default:
        throw new Exception("Unknown age '{$age}'!");
    }

    return $this;
  }

  public function addAction(PHUIListItemView $action) {
    if (count($this->actions) >= 3) {
      throw new Exception('Limit 3 actions per item.');
    }
    $this->actions[] = $action;
    return $this;
  }

  public function addIcon($icon, $label = null, $attributes = array()) {
    $this->icons[] = array(
      'icon'  => $icon,
      'label' => $label,
      'attributes' => $attributes,
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
    if (!empty($attribute)) {
      $this->attributes[] = $attribute;
    }
    return $this;
  }

  protected function getTagName() {
    return 'li';
  }

  protected function getTagAttributes() {
    $item_classes = array();
    $item_classes[] = 'phui-object-item';

    if ($this->icons) {
      $item_classes[] = 'phui-object-item-with-icons';
    }

    if ($this->attributes) {
      $item_classes[] = 'phui-object-item-with-attrs';
    }

    if ($this->handleIcons) {
      $item_classes[] = 'phui-object-item-with-handle-icons';
    }

    if ($this->barColor) {
      $item_classes[] = 'phui-object-item-bar-color-'.$this->barColor;
    }

    if ($this->footIcons) {
      $item_classes[] = 'phui-object-item-with-foot-icons';
    }

    if ($this->actions) {
      $n = count($this->actions);
      $item_classes[] = 'phui-object-item-with-actions';
      $item_classes[] = 'phui-object-item-with-'.$n.'-actions';
    }

    if ($this->disabled) {
      $item_classes[] = 'phui-object-item-disabled';
    }

    if ($this->state) {
      $item_classes[] = 'phui-object-item-state-'.$this->state;
    }

    switch ($this->effect) {
      case 'highlighted':
        $item_classes[] = 'phui-object-item-highlighted';
        break;
      case 'selected':
        $item_classes[] = 'phui-object-item-selected';
        break;
      case null:
        break;
      default:
        throw new Exception(pht('Invalid effect!'));
    }

    if ($this->getGrippable()) {
      $item_classes[] = 'phui-object-item-grippable';
    }

    if ($this->getImageURI()) {
      $item_classes[] = 'phui-object-item-with-image';
    }

    if ($this->getImageIcon()) {
      $item_classes[] = 'phui-object-item-with-image-icon';
    }

    if ($this->fontIcon) {
      $item_classes[] = 'phui-object-item-with-ficon';
    }

    return array(
      'class' => $item_classes,
    );
  }

  protected function getTagContent() {
    $content_classes = array();
    $content_classes[] = 'phui-object-item-content';

    $header_name = null;
    if ($this->objectName) {
      $header_name = array(
        phutil_tag(
          'span',
          array(
            'class' => 'phui-object-item-objname',
          ),
          $this->objectName),
        ' ',
      );
    }

    $title_text = null;
    if ($this->titleText) {
      $title_text = $this->titleText;
    } else if ($this->href) {
      $title_text = $this->header;
    }

    $header_link = phutil_tag(
      $this->href ? 'a' : 'div',
      array(
        'href' => $this->href,
        'class' => 'phui-object-item-link',
        'title' => $title_text,
      ),
      $this->header);

    $header = javelin_tag(
      'div',
      array(
        'class' => 'phui-object-item-name',
        'sigil' => 'slippery',
      ),
      array(
        $this->headIcons,
        $header_name,
        $header_link,
      ));

    $icons = array();
    if ($this->icons) {
      $icon_list = array();
      foreach ($this->icons as $spec) {
        $icon = $spec['icon'];
        $icon = id(new PHUIIconView())
          ->setIconFont($icon)
          ->addClass('phui-object-item-icon-image');

        if (isset($spec['attributes']['tip'])) {
          $sigil = 'has-tooltip';
          $meta = array(
            'tip' => $spec['attributes']['tip'],
            'align' => 'W',
          );
          $icon->addSigil($sigil);
          $icon->setMetadata($meta);
        }

        $label = phutil_tag(
          'span',
          array(
            'class' => 'phui-object-item-icon-label',
          ),
          $spec['label']);

        if (isset($spec['attributes']['href'])) {
          $icon_href = phutil_tag(
            'a',
            array('href' => $spec['attributes']['href']),
            array($icon, $label));
        } else {
          $icon_href = array($icon, $label);
        }

        $classes = array();
        $classes[] = 'phui-object-item-icon';
        if (isset($spec['attributes']['class'])) {
          $classes[] = $spec['attributes']['class'];
        }

        $icon_list[] = javelin_tag(
          'li',
          array(
            'class' => implode(' ', $classes),
          ),
          $icon_href);
      }

      $icons[] = phutil_tag(
        'ul',
        array(
          'class' => 'phui-object-item-icons',
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
          'class' => 'phui-object-item-handle-icons',
        ),
        $handle_bar);
    }

    $bylines = array();
    if ($this->bylines) {
      foreach ($this->bylines as $byline) {
        $bylines[] = phutil_tag(
          'div',
          array(
            'class' => 'phui-object-item-byline',
          ),
          $byline);
      }
      $bylines = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-bylines',
        ),
        $bylines);
    }

    $subhead = null;
    if ($this->subhead) {
      $subhead = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-subhead',
        ),
        $this->subhead);
    }

    if ($icons) {
      $icons = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-icon-pane',
        ),
        $icons);
    }

    $attrs = null;
    if ($this->attributes) {
      $attrs = array();
      $spacer = phutil_tag(
        'span',
        array(
          'class' => 'phui-object-item-attribute-spacer',
        ),
        "\xC2\xB7");
      $first = true;
      foreach ($this->attributes as $attribute) {
        $attrs[] = phutil_tag(
          'li',
          array(
            'class' => 'phui-object-item-attribute',
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
          'class' => 'phui-object-item-attributes',
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
          'class' => 'phui-object-item-foot-icons',
        ),
        $foot_bar);
    }

    $grippable = null;
    if ($this->getGrippable()) {
      $grippable = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-grip',
        ),
        '');
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $content_classes),
      ),
      array(
        $subhead,
        $attrs,
        $this->renderChildren(),
        $foot,
      ));

    $image = null;
    if ($this->getImageURI()) {
      $image = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-image',
          'style' => 'background-image: url('.$this->getImageURI().')',
        ),
        '');
    } else if ($this->getImageIcon()) {
      $image = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-image-icon',
        ),
        $this->getImageIcon());
    }

    $ficon = null;
    if ($this->fontIcon) {
      $image = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-ficon',
        ),
        $this->fontIcon);
    }

    if ($image && $this->href) {
      $image = phutil_tag(
        'a',
        array(
          'href' => $this->href,
        ),
        $image);
    }

    /* Build a fake table */
    $column1 = phutil_tag(
      'div',
      array(
        'class' => 'phui-object-item-col1',
      ),
      array(
        $header,
        $content,
      ));

    $column2 = null;
    if ($icons || $bylines) {
      $column2 = phutil_tag(
        'div',
        array(
          'class' => 'phui-object-item-col2',
        ),
        array(
          $icons,
          $bylines,
        ));
    }

    $table = phutil_tag(
      'div',
      array(
        'class' => 'phui-object-item-table',
      ),
      phutil_tag_div('phui-object-item-table-row', array($column1, $column2)));

    $box = phutil_tag(
      'div',
      array(
        'class' => 'phui-object-item-content-box',
      ),
      array(
        $grippable,
        $table,
      ));

    $actions = array();
    if ($this->actions) {
      Javelin::initBehavior('phabricator-tooltips');

      foreach (array_reverse($this->actions) as $action) {
        $action->setRenderNameAsTooltip(true);
        $actions[] = $action;
      }
      $actions = phutil_tag(
        'ul',
        array(
          'class' => 'phui-object-item-actions',
        ),
        $actions);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-object-item-frame',
      ),
      array(
        $actions,
        $image,
        $box,
      ));
  }

  private function renderFootIcon($icon, $label) {

    $icon = id(new PHUIIconView())
      ->setIconFont($icon);

    $label = phutil_tag(
      'span',
      array(
      ),
      $label);

    return phutil_tag(
      'span',
      array(
        'class' => 'phui-object-item-foot-icon',
      ),
      array($icon, $label));
  }


  private function renderHandleIcon(PhabricatorObjectHandle $handle, $label) {
    Javelin::initBehavior('phabricator-tooltips');

    $options = array(
      'class' => 'phui-object-item-handle-icon',
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
