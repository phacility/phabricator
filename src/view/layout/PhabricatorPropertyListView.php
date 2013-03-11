<?php

final class PhabricatorPropertyListView extends AphrontView {

  private $parts = array();
  private $hasKeyboardShortcuts;
  private $object;
  private $invokedWillRenderEvent;

  protected function canAppendChild() {
    return false;
  }

  public function setObject($object) {
    $this->object = $object;
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

  public function addSectionHeader($name) {
    $this->parts[] = array(
      'type' => 'section',
      'name' => $name,
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

    require_celerity_resource('phabricator-property-list-view-css');

    $items = array();
    foreach ($this->parts as $part) {
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
        default:
          throw new Exception(pht("Unknown part type '%s'!", $type));
      }
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-property-list-view',
      ),
      $items);
  }

  private function renderPropertyPart(array $part) {
    $items = array();
    foreach ($part['list'] as $spec) {
      $key = $spec['key'];
      $value = $spec['value'];

      $items[] = phutil_tag(
        'dt',
        array(
          'class' => 'phabricator-property-list-key',
        ),
        $key);

      $items[] = phutil_tag(
        'dd',
        array(
          'class' => 'phabricator-property-list-value',
        ),
        $value);
    }

    $list = phutil_tag(
      'dl',
      array(
        'class' => 'phabricator-property-list-properties',
      ),
      $items);

    $shortcuts = null;
    if ($this->hasKeyboardShortcuts) {
      $shortcuts = new AphrontKeyboardShortcutsAvailableView();
    }

    return array(
      $shortcuts,
      phutil_tag(
        'div',
        array(
          'class' => 'phabricator-property-list-container',
        ),
        array(
          $list,
          phutil_tag(
            'div',
            array('class' => 'phabriator-property-list-view-end'),
            ''),
        )));
  }

  private function renderSectionPart(array $part) {
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-property-list-section-header',
      ),
      $part['name']);
  }

  private function renderTextPart(array $part) {
    $classes = array();
    $classes[] = 'phabricator-property-list-text-content';
    if ($part['type'] == 'image') {
      $classes[] = 'phabricator-property-list-image-content';
      $classes[] = 'phabricator-remarkup-dark';
    }
    return phutil_tag(
      'div',
      array(
        'class' => implode($classes, ' '),
      ),
      $part['content']);
  }

}
