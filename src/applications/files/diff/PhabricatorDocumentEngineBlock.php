<?php

final class PhabricatorDocumentEngineBlock
  extends Phobject {

  private $content;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function newContentView() {
    return $this->getContent();
  }

}
