<?php

abstract class PhabricatorDocumentEngine
  extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function canRenderDocument(PhabricatorDocumentRef $ref) {
    return $this->canRenderDocumentType($ref);
  }

  abstract protected function canRenderDocumentType(
    PhabricatorDocumentRef $ref);

  final public function newDocument(PhabricatorDocumentRef $ref) {
    return $this->newDocumentContent($ref);
  }

  final public function newDocumentIcon(PhabricatorDocumentRef $ref) {
    return id(new PHUIIconView())
      ->setIcon($this->getDocumentIconIcon($ref));
  }

  abstract protected function newDocumentContent(
    PhabricatorDocumentRef $ref);

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-file-o';
  }

  final public function getDocumentEngineKey() {
    return $this->getPhobjectClassConstant('ENGINEKEY');
  }

  final public static function getAllEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getDocumentEngineKey')
      ->execute();
  }

  final public function newSortVector(PhabricatorDocumentRef $ref) {
    $content_score = $this->getContentScore($ref);

    return id(new PhutilSortVector())
      ->addInt(-$content_score);
  }

  protected function getContentScore() {
    return 2000;
  }

}
