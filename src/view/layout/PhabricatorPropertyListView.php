<?php

final class PhabricatorPropertyListView extends AphrontView {

  private $properties = array();

  public function addProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function addTextContent($content) {
    return $this->appendChild(
      phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-property-list-text-content',
        ),
        $content));
  }

  public function render() {
    require_celerity_resource('phabricator-property-list-view-css');

    $items = array();
    foreach ($this->properties as $key => $value) {
      $items[] = phutil_render_tag(
        'dt',
        array(
          'class' => 'phabricator-property-key',
        ),
        phutil_escape_html($key));
      $items[] = phutil_render_tag(
        'dd',
        array(
          'class' => 'phabricator-property-value',
        ),
        $this->renderSingleView($value));
    }

    $list = phutil_render_tag(
      'dl',
      array(
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

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-property-list-view',
      ),
      $list.
      // NOTE: We need this (which is basically a "clear: both;" div) to make
      // sure the property list is taller than the action list for objects with
      // few properties but many actions. Otherwise, the action list may
      // obscure the document content.
      '<div class="phabriator-property-list-view-end"></div>').
      $content;
  }


}
