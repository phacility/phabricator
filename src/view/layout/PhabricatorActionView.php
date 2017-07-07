<?php

final class PhabricatorActionView extends AphrontView {

  private $name;
  private $icon;
  private $href;
  private $disabled;
  private $label;
  private $workflow;
  private $renderAsForm;
  private $download;
  private $sigils = array();
  private $metadata;
  private $selected;
  private $openInNewWindow;
  private $submenu = array();
  private $hidden;
  private $depth;
  private $id;
  private $order;
  private $color;
  private $type;

  const TYPE_DIVIDER  = 'type-divider';
  const TYPE_LABEL  = 'label';
  const RED = 'action-item-red';

  public function setSelected($selected) {
    $this->selected = $selected;
    return $this;
  }

  public function getSelected() {
    return $this->selected;
  }

  public function setMetadata($metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
  }

  public function setDownload($download) {
    $this->download = $download;
    return $this;
  }

  public function getDownload() {
    return $this->download;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function addSigil($sigil) {
    $this->sigils[] = $sigil;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setLabel($label) {
    $this->label = $label;
    return $this;
  }

  public function setDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function getDisabled() {
    return $this->disabled;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function setRenderAsForm($form) {
    $this->renderAsForm = $form;
    return $this;
  }

  public function setOpenInNewWindow($open_in_new_window) {
    $this->openInNewWindow = $open_in_new_window;
    return $this;
  }

  public function getOpenInNewWindow() {
    return $this->openInNewWindow;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function getOrder() {
    return $this->order;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setSubmenu(array $submenu) {
    $this->submenu = $submenu;

    if (!$this->getHref()) {
      $this->setHref('#');
    }

    return $this;
  }

  public function getItems($depth = 0) {
    $items = array();

    $items[] = $this;
    foreach ($this->submenu as $action) {
      foreach ($action->getItems($depth + 1) as $item) {
        $item
          ->setHidden(true)
          ->setDepth($depth + 1);

        $items[] = $item;
      }
    }

    return $items;
  }

  public function setHidden($hidden) {
    $this->hidden = $hidden;
    return $this;
  }

  public function getHidden() {
    return $this->hidden;
  }

  public function setDepth($depth) {
    $this->depth = $depth;
    return $this;
  }

  public function getDepth() {
    return $this->depth;
  }

  public function render() {
    $caret_id = celerity_generate_unique_node_id();

    $icon = null;
    if ($this->icon) {
      $color = '';
      if ($this->disabled) {
        $color = ' grey';
      }
      $icon = id(new PHUIIconView())
        ->addClass('phabricator-action-view-icon')
        ->setIcon($this->icon.$color);
    }

    $sigils = array();
    if ($this->workflow) {
      $sigils[] = 'workflow';
    }

    if ($this->download) {
      $sigils[] = 'download';
    }

    if ($this->submenu) {
      $sigils[] = 'keep-open';
    }

    if ($this->sigils) {
      $sigils = array_merge($sigils, $this->sigils);
    }

    $sigils = $sigils ? implode(' ', $sigils) : null;

    if ($this->href) {
      if ($this->renderAsForm) {
        if (!$this->hasViewer()) {
          throw new Exception(
            pht(
              'Call %s when rendering an action as a form.',
              'setViewer()'));
        }

        $item = javelin_tag(
          'button',
          array(
            'class' => 'phabricator-action-view-item',
          ),
          array($icon, $this->name));

        $item = phabricator_form(
          $this->getViewer(),
          array(
            'action'    => $this->getHref(),
            'method'    => 'POST',
            'sigil'     => $sigils,
            'meta'      => $this->metadata,
          ),
          $item);
      } else {
        if ($this->getOpenInNewWindow()) {
          $target = '_blank';
        } else {
          $target = null;
        }

        if ($this->submenu) {
          $caret = javelin_tag(
            'span',
            array(
              'class' => 'caret-right',
              'id' => $caret_id,
            ),
            '');
        } else {
          $caret = null;
        }

        $item = javelin_tag(
          'a',
          array(
            'href'  => $this->getHref(),
            'class' => 'phabricator-action-view-item',
            'target' => $target,
            'sigil' => $sigils,
            'meta' => $this->metadata,
          ),
          array($icon, $this->name, $caret));
      }
    } else {
      $item = javelin_tag(
        'span',
        array(
          'class' => 'phabricator-action-view-item',
          'sigil' => $sigils,
        ),
        array($icon, $this->name, $this->renderChildren()));
    }

    $classes = array();
    $classes[] = 'phabricator-action-view';

    if ($this->disabled) {
      $classes[] = 'phabricator-action-view-disabled';
    }

    if ($this->label) {
      $classes[] = 'phabricator-action-view-label';
    }

    if ($this->selected) {
      $classes[] = 'phabricator-action-view-selected';
    }

    if ($this->submenu) {
      $classes[] = 'phabricator-action-view-submenu';
    }

    if ($this->getHref()) {
      $classes[] = 'phabricator-action-view-href';
    }

    if ($this->icon) {
      $classes[] = 'action-has-icon';
    }

    if ($this->color) {
      $classes[] = $this->color;
    }

    if ($this->type) {
      $classes[] = 'phabricator-action-view-'.$this->type;
    }

    $style = array();

    if ($this->hidden) {
      $style[] = 'display: none;';
    }

    if ($this->depth) {
      $indent = ($this->depth * 16);
      $style[] = "margin-left: {$indent}px;";
    }

    $sigil = null;
    $meta = null;

    if ($this->submenu) {
      Javelin::initBehavior('phui-submenu');
      $sigil = 'phui-submenu';

      $item_ids = array();
      foreach ($this->submenu as $subitem) {
        $item_ids[] = $subitem->getID();
      }

      $meta = array(
        'itemIDs' => $item_ids,
        'caretID' => $caret_id,
      );
    }

    return javelin_tag(
      'li',
      array(
        'id' => $this->getID(),
        'class' => implode(' ', $classes),
        'style' => implode(' ', $style),
        'sigil' => $sigil,
        'meta' => $meta,
      ),
      $item);
  }

}
