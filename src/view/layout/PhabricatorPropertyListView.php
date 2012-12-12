<?php

final class PhabricatorPropertyListView extends AphrontView {

  private $parts = array();
  private $hasKeyboardShortcuts;

  protected function canAppendChild() {
    return false;
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

  public function render() {
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
          $items[] = $this->renderTextPart($part);
          break;
        default:
          throw new Exception("Unknown part type '{$type}'!");
      }
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-property-list-view',
      ),
      $this->renderSingleView($items));
  }

  private function renderPropertyPart(array $part) {
    $items = array();
    foreach ($part['list'] as $spec) {
      $key = $spec['key'];
      $value = $spec['value'];

      $items[] = phutil_render_tag(
        'dt',
        array(
          'class' => 'phabricator-property-list-key',
        ),
        phutil_escape_html($key));
      $items[] = phutil_render_tag(
        'dd',
        array(
          'class' => 'phabricator-property-list-value',
        ),
        $this->renderSingleView($value));
    }

    $list = phutil_render_tag(
      'dl',
      array(
        'class' => 'phabricator-property-list-properties',
      ),
      $this->renderSingleView($items));

    $content = $this->renderChildren();
    if (strlen($content)) {
      $content = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-property-list-content',
        ),
        $content);
    }

    $shortcuts = null;
    if ($this->hasKeyboardShortcuts) {
      $shortcuts =
        id(new AphrontKeyboardShortcutsAvailableView())->render();
    }

    return
      $shortcuts.
      phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-property-list-container',
        ),
        $list.
        '<div class="phabriator-property-list-view-end"></div>'
      );
  }

  private function renderSectionPart(array $part) {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-property-list-section-header',
      ),
      phutil_escape_html($part['name']));
  }

  private function renderTextPart(array $part) {
    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-property-list-text-content',
      ),
      $part['content']);
  }

}
