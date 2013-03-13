<?php

final class PasteEmbedView extends AphrontView {

  private $paste;
  private $handle;

  public function setPaste(PhabricatorPaste $paste) {
    $this->paste = $paste;
    return $this;
  }

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function render() {
    if (!$this->paste) {
      throw new Exception("Call setPaste() before render()!");
    }

    $lines = phutil_split_lines($this->paste->getContent());
    require_celerity_resource('paste-css');

    $link = phutil_tag(
      'a',
      array(
        'href' => '/P'.$this->paste->getID()
      ),
      $this->handle->getFullName());

    $head = phutil_tag(
      'div',
      array(
        'class' => 'paste-embed-head'
      ),
      $link);

    $body = phutil_tag(
      'div',
      array(),
      id(new PhabricatorSourceCodeView())
      ->setLines($lines));

    return phutil_tag(
      'div',
      array(
        'class' => 'paste-embed'
      ),
      array($head, $body));

  }
}
