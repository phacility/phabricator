<?php

abstract class PhabricatorSearchEngineAttachment extends Phobject {

  private $attachmentKey;
  private $viewer;
  private $searchEngine;

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setSearchEngine(
    PhabricatorApplicationSearchEngine $engine) {
    $this->searchEngine = $engine;
    return $this;
  }

  final public function getSearchEngine() {
    return $this->searchEngine;
  }

  public function setAttachmentKey($attachment_key) {
    $this->attachmentKey = $attachment_key;
    return $this;
  }

  public function getAttachmentKey() {
    return $this->attachmentKey;
  }

  abstract public function getAttachmentName();
  abstract public function getAttachmentDescription();

  public function willLoadAttachmentData($query, $spec) {
    return;
  }

  public function loadAttachmentData(array $objects, $spec) {
    return null;
  }

  abstract public function getAttachmentForObject($object, $data, $spec);

}
