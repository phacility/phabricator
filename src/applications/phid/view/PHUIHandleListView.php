<?php

/**
 * Convenience class for rendering a list of handles.
 *
 * This class simplifies rendering a list of handles and improves loading and
 * caching semantics in the rendering pipeline by delaying bulk loads until the
 * last possible moment.
 */
final class PHUIHandleListView
  extends AphrontTagView {

  private $handleList;
  private $asInline;
  private $asText;

  public function setHandleList(PhabricatorHandleList $list) {
    $this->handleList = $list;
    return $this;
  }

  public function setAsInline($inline) {
    $this->asInline = $inline;
    return $this;
  }

  public function getAsInline() {
    return $this->asInline;
  }

  public function setAsText($as_text) {
    $this->asText = $as_text;
    return $this;
  }

  public function getAsText() {
    return $this->asText;
  }

  protected function getTagName() {
    if ($this->getAsText()) {
      return null;
    } else {
      // TODO: It would be nice to render this with a proper <ul />, at least
      // in block mode, but don't stir the waters up too much for now.
      return 'span';
    }
  }

  protected function getTagContent() {
    $list = $this->handleList;
    $items = array();
    foreach ($list as $handle) {
      $view = $list->renderHandle($handle->getPHID())
        ->setShowHovercard(true)
        ->setAsText($this->getAsText());

      $items[] = $view;
    }

    if ($this->getAsInline()) {
      $items = phutil_implode_html(', ', $items);
    } else {
      $items = phutil_implode_html(phutil_tag('br'), $items);
    }

    return $items;
  }

}
