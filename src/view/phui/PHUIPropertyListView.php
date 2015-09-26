<?php

final class PHUIPropertyListView extends AphrontView {

  private $parts = array();
  private $hasKeyboardShortcuts;
  private $object;
  private $invokedWillRenderEvent;
  private $actionList = null;
  private $classes = array();
  private $stacked;

  const ICON_SUMMARY = 'fa-align-left';
  const ICON_TESTPLAN = 'fa-file-text-o';

  protected function canAppendChild() {
    return false;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function setActionList(PhabricatorActionListView $list) {
    $this->actionList = $list;
    return $this;
  }

  public function getActionList() {
    return $this->actionList;
  }

  public function setStacked($stacked) {
    $this->stacked = $stacked;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setHasKeyboardShortcuts($has_keyboard_shortcuts) {
    $this->hasKeyboardShortcuts = $has_keyboard_shortcuts;
    return $this;
  }

  public function addProperty($key, $value) {
    $current = array_pop($this->parts);

    if (!$current || $current['type'] != 'property') {
      if ($current) {
        $this->parts[] = $current;
      }
      $current = array(
        'type' => 'property',
        'list' => array(),
      );
    }

    $current['list'][] = array(
      'key'   => $key,
      'value' => $value,
    );

    $this->parts[] = $current;
    return $this;
  }

  public function addSectionHeader($name, $icon = null) {
    $this->parts[] = array(
      'type' => 'section',
      'name' => $name,
      'icon' => $icon,
    );
    return $this;
  }

  public function addTextContent($content) {
    $this->parts[] = array(
      'type'    => 'text',
      'content' => $content,
    );
    return $this;
  }

  public function addRawContent($content) {
    $this->parts[] = array(
      'type'    => 'raw',
      'content' => $content,
    );
    return $this;
  }

  public function addImageContent($content) {
    $this->parts[] = array(
      'type'    => 'image',
      'content' => $content,
    );
    return $this;
  }

  public function invokeWillRenderEvent() {
    if ($this->object && $this->getUser() && !$this->invokedWillRenderEvent) {
      $event = new PhabricatorEvent(
        PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES,
        array(
          'object'  => $this->object,
          'view'    => $this,
        ));
      $event->setUser($this->getUser());
      PhutilEventEngine::dispatchEvent($event);
    }
    $this->invokedWillRenderEvent = true;
  }

  public function render() {
    $this->invokeWillRenderEvent();

    require_celerity_resource('phui-property-list-view-css');

    $items = array();

    $parts = $this->parts;

    // If we have an action list, make sure we render a property part, even
    // if there are no properties. Otherwise, the action list won't render.
    if ($this->actionList) {
      $have_property_part = false;
      foreach ($this->parts as $part) {
        if ($part['type'] == 'property') {
          $have_property_part = true;
          break;
        }
      }
      if (!$have_property_part) {
        $parts[] = array(
          'type' => 'property',
          'list' => array(),
        );
      }
    }

    foreach ($parts as $part) {
      $type = $part['type'];
      switch ($type) {
        case 'property':
          $items[] = $this->renderPropertyPart($part);
          break;
        case 'section':
          $items[] = $this->renderSectionPart($part);
          break;
        case 'text':
        case 'image':
          $items[] = $this->renderTextPart($part);
          break;
        case 'raw':
          $items[] = $this->renderRawPart($part);
          break;
        default:
          throw new Exception(pht("Unknown part type '%s'!", $type));
      }
    }
    $this->classes[] = 'phui-property-list-section';
    $classes = implode(' ', $this->classes);

    return phutil_tag(
      'div',
      array(
        'class' => $classes,
      ),
      array(
        $items,
      ));
  }

  private function renderPropertyPart(array $part) {
    $items = array();
    foreach ($part['list'] as $spec) {
      $key = $spec['key'];
      $value = $spec['value'];

      // NOTE: We append a space to each value to improve the behavior when the
      // user double-clicks a property value (like a URI) to select it. Without
      // the space, the label is also selected.

      $items[] = phutil_tag(
        'dt',
        array(
          'class' => 'phui-property-list-key',
        ),
        array($key, ' '));

      $items[] = phutil_tag(
        'dd',
        array(
          'class' => 'phui-property-list-value',
        ),
        array($value, ' '));
    }

    $stacked = '';
    if ($this->stacked) {
      $stacked = 'phui-property-list-stacked';
    }

    $list = phutil_tag(
      'dl',
      array(
        'class' => 'phui-property-list-properties',
      ),
      $items);

    $shortcuts = null;
    if ($this->hasKeyboardShortcuts) {
      $shortcuts = new AphrontKeyboardShortcutsAvailableView();
    }

    $list = phutil_tag(
      'div',
      array(
        'class' => 'phui-property-list-properties-wrap '.$stacked,
      ),
      array($shortcuts, $list));

    $action_list = null;
    if ($this->actionList) {
      $action_list = phutil_tag(
        'div',
        array(
          'class' => 'phui-property-list-actions',
        ),
        $this->actionList);
      $this->actionList = null;
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-property-list-container grouped',
      ),
      array($action_list, $list));
  }

  private function renderSectionPart(array $part) {
    $name = $part['name'];
    if ($part['icon']) {
      $icon = id(new PHUIIconView())
        ->setIconFont($part['icon'].' bluegrey');
      $name = phutil_tag(
        'span',
        array(
          'class' => 'phui-property-list-section-header-icon',
        ),
        array($icon, $name));
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phui-property-list-section-header',
      ),
      $name);
  }

  private function renderTextPart(array $part) {
    $classes = array();
    $classes[] = 'phui-property-list-text-content';
    if ($part['type'] == 'image') {
      $classes[] = 'phui-property-list-image-content';
    }
    return phutil_tag(
      'div',
      array(
        'class' => implode($classes, ' '),
      ),
      $part['content']);
  }

  private function renderRawPart(array $part) {
    $classes = array();
    $classes[] = 'phui-property-list-raw-content';
    return phutil_tag(
      'div',
      array(
        'class' => implode($classes, ' '),
      ),
      $part['content']);
  }

}
