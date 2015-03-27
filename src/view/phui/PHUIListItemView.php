<?php

final class PHUIListItemView extends AphrontTagView {

  const TYPE_LINK     = 'type-link';
  const TYPE_SPACER   = 'type-spacer';
  const TYPE_LABEL    = 'type-label';
  const TYPE_BUTTON   = 'type-button';
  const TYPE_CUSTOM   = 'type-custom';
  const TYPE_DIVIDER  = 'type-divider';
  const TYPE_ICON     = 'type-icon';
  const TYPE_ICON_NAV = 'type-icon-nav';

  const STATUS_WARN   = 'phui-list-item-warn';
  const STATUS_FAIL   = 'phui-list-item-fail';

  private $name;
  private $href;
  private $type = self::TYPE_LINK;
  private $isExternal;
  private $key;
  private $icon;
  private $appIcon;
  private $selected;
  private $disabled;
  private $renderNameAsTooltip;
  private $statusColor;
  private $order;
  private $aural;
  private $profileImage;

  public function setAural($aural) {
    $this->aural = $aural;
    return $this;
  }

  public function getAural() {
    return $this->aural;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function getOrder() {
    return $this->order;
  }

  public function setRenderNameAsTooltip($render_name_as_tooltip) {
    $this->renderNameAsTooltip = $render_name_as_tooltip;
    return $this;
  }

  public function getRenderNameAsTooltip() {
    return $this->renderNameAsTooltip;
  }

  public function setSelected($selected) {
    $this->selected = $selected;
    return $this;
  }

  public function getSelected() {
    return $this->selected;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setProfileImage($image) {
    $this->profileImage = $image;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setKey($key) {
    $this->key = (string)$key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIsExternal($is_external) {
    $this->isExternal = $is_external;
    return $this;
  }

  public function getIsExternal() {
    return $this->isExternal;
  }

  public function setStatusColor($color) {
    $this->statusColor = $color;
    return $this;
  }

  protected function getTagName() {
    return 'li';
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-list-item-view';
    $classes[] = 'phui-list-item-'.$this->type;

    if ($this->icon || $this->appIcon) {
      $classes[] = 'phui-list-item-has-icon';
    }

    if ($this->selected) {
      $classes[] = 'phui-list-item-selected';
    }

    if ($this->disabled) {
      $classes[] = 'phui-list-item-disabled';
    }

    if ($this->statusColor) {
      $classes[] = $this->statusColor;
    }

    return array(
      'class' => $classes,
    );
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  protected function getTagContent() {
    $name = null;
    $icon = null;
    $meta = null;
    $sigil = null;

    if ($this->name) {
      if ($this->getRenderNameAsTooltip()) {
        Javelin::initBehavior('phabricator-tooltips');
        $sigil = 'has-tooltip';
        $meta = array(
          'tip' => $this->name,
          'align' => 'E',
        );
      } else {
        $external = null;
        if ($this->isExternal) {
          $external = " \xE2\x86\x97";
        }

        // If this element has an aural representation, make any name visual
        // only. This is primarily dealing with the links in the main menu like
        // "Profile" and "Logout". If we don't hide the name, the mobile
        // version of these elements will have two redundant names.

        $classes = array();
        $classes[] = 'phui-list-item-name';
        if ($this->aural !== null) {
          $classes[] = 'visual-only';
        }

        $name = phutil_tag(
          'span',
          array(
            'class' => implode(' ', $classes),
          ),
          array(
            $this->name,
            $external,
          ));
      }
    }

    $aural = null;
    if ($this->aural !== null) {
      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        $this->aural);
    }

    if ($this->icon) {
      $icon_name = $this->icon;
      if ($this->getDisabled()) {
        $icon_name .= ' grey';
      }

      $icon = id(new PHUIIconView())
        ->addClass('phui-list-item-icon')
        ->setIconFont($icon_name);
    }

    if ($this->profileImage) {
      $icon = id(new PHUIIconView())
        ->setHeadSize(PHUIIconView::HEAD_SMALL)
        ->setImage($this->profileImage);
    }

    if ($this->appIcon) {
      $icon = id(new PHUIIconView())
        ->addClass('phui-list-item-icon')
        ->setIconFont($this->appIcon);
    }

    return javelin_tag(
      $this->href ? 'a' : 'div',
      array(
        'href' => $this->href,
        'class' => $this->href ? 'phui-list-item-href' : null,
        'meta' => $meta,
        'sigil' => $sigil,
      ),
      array(
        $aural,
        $icon,
        $this->renderChildren(),
        $name,
      ));
  }

}
