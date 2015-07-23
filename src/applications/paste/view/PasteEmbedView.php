<?php

final class PasteEmbedView extends AphrontView {

  private $paste;
  private $handle;
  private $highlights = array();
  private $lines = 24;

  public function setPaste(PhabricatorPaste $paste) {
    $this->paste = $paste;
    return $this;
  }

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setHighlights(array $highlights) {
    $this->highlights = $highlights;
    return $this;
  }

  public function setLines($lines) {
    $this->lines = $lines;
    return $this;
  }

  public function render() {
    if (!$this->paste) {
      throw new PhutilInvalidStateException('setPaste');
    }

    $lines = phutil_split_lines($this->paste->getContent());
    require_celerity_resource('paste-css');

    $link = phutil_tag(
      'a',
      array(
        'href' => '/P'.$this->paste->getID(),
      ),
      $this->handle->getFullName());

    $head = phutil_tag(
      'div',
      array(
        'class' => 'paste-embed-head',
      ),
      $link);

    $body_attributes = array('class' => 'paste-embed-body');
    if ($this->lines != null) {
      $body_attributes['style'] = 'max-height: '.$this->lines * (1.15).'em;';
    }

    $body = phutil_tag(
      'div',
      $body_attributes,
      id(new PhabricatorSourceCodeView())
      ->setLines($lines)
      ->setHighlights($this->highlights)
      ->disableHighlightOnClick());

    return phutil_tag(
      'div',
      array('class' => 'paste-embed'),
      array($head, $body));

  }
}
