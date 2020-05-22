<?php

abstract class PhabricatorInlineCommentContentState
  extends Phobject {

  private $contentText = '';

  public function setContentText($content_text) {
    $this->contentText = $content_text;
    return $this;
  }

  public function getContentText() {
    return $this->contentText;
  }

  public function isEmptyContentState() {
    return !strlen($this->getContentText());
  }

  public function writeStorageMap() {
    return array(
      'text' => $this->getContentText(),
    );
  }

  public function readStorageMap(array $map) {
    $text = (string)idx($map, 'text');
    $this->setContentText($text);

    return $this;
  }

  final public function readFromRequest(AphrontRequest $request) {
    $map = $this->newStorageMapFromRequest($request);
    return $this->readStorageMap($map);
  }

  protected function newStorageMapFromRequest(AphrontRequest $request) {
    $map = array();

    $map['text'] = (string)$request->getStr('text');

    return $map;
  }

}
