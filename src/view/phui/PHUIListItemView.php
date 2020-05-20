<?php

final class PHUIListItemView extends AphrontTagView {

  const TYPE_LINK     = 'type-link';
  const TYPE_SPACER   = 'type-spacer';
  const TYPE_LABEL    = 'type-label';
  const TYPE_BUTTON   = 'type-button';
  const TYPE_CUSTOM   = 'type-custom';
  const TYPE_DIVIDER  = 'type-divider';
  const TYPE_ICON     = 'type-icon';

  const STATUS_WARN   = 'phui-list-item-warn';
  const STATUS_FAIL   = 'phui-list-item-fail';

  private $name;
  private $href;
  private $type = self::TYPE_LINK;
  private $isExternal;
  private $key;
  private $icon;
  private $selected;
  private $disabled;
  private $renderNameAsTooltip;
  private $statusColor;
  private $order;
  private $aural;
  private $profileImage;
  private $indented;
  private $hideInApplicationMenu;
  private $icons = array();
  private $openInNewWindow = false;
  private $tooltip;
  private $actionIcon;
  private $actionIconHref;
  private $count;
  private $rel;
  private $dropdownMenu;
  private $keyCommand;

  public function setOpenInNewWindow($open_in_new_window) {
    $this->openInNewWindow = $open_in_new_window;
    return $this;
  }

  public function getOpenInNewWindow() {
    return $this->openInNewWindow;
  }

  public function setRel($rel) {
    $this->rel = $rel;
    return $this;
  }

  public function getRel() {
    return $this->rel;
  }

  public function setHideInApplicationMenu($hide) {
    $this->hideInApplicationMenu = $hide;
    return $this;
  }

  public function getHideInApplicationMenu() {
    return $this->hideInApplicationMenu;
  }

  public function setDropdownMenu(PhabricatorActionListView $actions) {

    $this->dropdownMenu = $actions;

    // TODO: "PHUICrumbsView" currently creates a bad copy of list items
    // by reading some of their properties. To survive this copy step, we
    // need to mutate "$this" immediately or the "Create Object" dropdown
    // when multiple create forms exist breaks.

    if (!$this->actionIcon) {
      Javelin::initBehavior('phui-dropdown-menu');
      $this->addSigil('phui-dropdown-menu');
      $this->setMetadata($actions->getDropdownMenuMetadata());
    }

    return $this;
  }

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

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function setIndented($indented) {
    $this->indented = $indented;
    return $this;
  }

  public function getIndented() {
    return $this->indented;
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

  public function setActionIcon($icon, $href) {
    $this->actionIcon = $icon;
    $this->actionIconHref = $href;
    return $this;
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

  public function addIcon($icon) {
    $this->icons[] = $icon;
    return $this;
  }

  public function getIcons() {
    return $this->icons;
  }

  public function setTooltip($tooltip) {
    $this->tooltip = $tooltip;
    return $this;
  }

  protected function getTagName() {
    return 'li';
  }

  public function setKeyCommand($key_command) {
    $this->keyCommand = $key_command;
    return $this;
  }

  public function getKeyCommand() {
    return $this->keyCommand;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-list-item-view';
    $classes[] = 'phui-list-item-'.$this->type;

    if ($this->icon || $this->profileImage) {
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

    if ($this->actionIcon) {
      $classes[] = 'phui-list-item-has-action-icon';
    }

    $sigil = null;
    $metadata = null;
    if ($this->dropdownMenu) {
      $classes[] = 'dropdown';
      if (!$this->actionIcon) {
        $classes[] = 'dropdown-with-caret';
        Javelin::initBehavior('phui-dropdown-menu');
        $sigil = 'phui-dropdown-menu';
        $metadata = $this->dropdownMenu->getDropdownMenuMetadata();
      }
    }

    return array(
      'class' => $classes,
      'sigil' => $sigil,
      'meta' => $metadata,
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
    $sigil = array();

    if ($this->name) {
      if ($this->getRenderNameAsTooltip()) {
        Javelin::initBehavior('phabricator-tooltips');
        $sigil[] = 'has-tooltip';
        $meta = array(
          'tip' => $this->name,
          'align' => 'E',
        );
      } else {
        if ($this->tooltip) {
          Javelin::initBehavior('phabricator-tooltips');
          $sigil[] = 'has-tooltip';
          $meta = array(
            'tip' => $this->tooltip,
            'align' => 'E',
            'size' => 300,
          );
        }

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
        ->setIcon($icon_name);
    }

    if ($this->profileImage) {
      $icon = id(new PHUIIconView())
        ->setHeadSize(PHUIIconView::HEAD_SMALL)
        ->addClass('phui-list-item-icon')
        ->setImage($this->profileImage);
    }

    $classes = array();
    if ($this->href) {
      $classes[] = 'phui-list-item-href';
    }

    if ($this->indented) {
      $classes[] = 'phui-list-item-indented';
    }

    $action_link = $this->newActionIconView();

    $count = null;
    if ($this->count) {
      $count = phutil_tag(
        'span',
        array(
          'class' => 'phui-list-item-count',
        ),
        $this->count);
    }

    $caret = null;
    if ($this->dropdownMenu && !$this->actionIcon) {
      $caret = id(new PHUIIconView())
        ->setIcon('fa-caret-down');
    }

    $icons = $this->getIcons();

    $key_command = null;
    if ($this->keyCommand) {
      $key_command = phutil_tag(
        'span',
        array(
          'class' => 'keyboard-shortcut-key',
        ),
        $this->keyCommand);
      $sigil[] = 'has-key-command';
      $meta['keyCommand'] = $this->keyCommand;
    }

    $list_item = javelin_tag(
      $this->href ? 'a' : 'div',
      array(
        'href' => $this->href,
        'class' => implode(' ', $classes),
        'meta' => $meta,
        'sigil' => implode(' ', $sigil),
        'target' => $this->getOpenInNewWindow() ? '_blank' : null,
        'rel' => $this->rel,
      ),
      array(
        $aural,
        $icon,
        $icons,
        $this->renderChildren(),
        $name,
        $count,
        $key_command,
        $caret,
      ));

    return array($list_item, $action_link);
  }

  private function newActionIconView() {
    $action_icon = $this->actionIcon;
    $action_href = $this->actionIconHref;

    if ($action_icon === null) {
      return null;
    }

    $icon_view = id(new PHUIIconView())
      ->setIcon($action_icon)
      ->addClass('phui-list-item-action-icon');

    if ($this->dropdownMenu) {
      Javelin::initBehavior('phui-dropdown-menu');
      $sigil = 'phui-dropdown-menu';
      $metadata = $this->dropdownMenu->getDropdownMenuMetadata();
    } else {
      $sigil = null;
      $metadata = null;
    }

    return javelin_tag(
      'a',
      array(
        'href' => $action_href,
        'class' => 'phui-list-item-action-href',
        'sigil' => $sigil,
        'meta' => $metadata,
      ),
      $icon_view);
  }

}
