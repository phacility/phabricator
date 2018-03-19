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

  abstract public function getViewAsLabel(PhabricatorDocumentRef $ref);

  public function getViewAsIconIcon(PhabricatorDocumentRef $ref) {
    return $this->getDocumentIconIcon($ref);
  }

  public function getRenderURI(PhabricatorDocumentRef $ref) {
    $file = $ref->getFile();
    if (!$file) {
      throw new PhutilMethodNotImplementedException();
    }

    $engine_key = $this->getDocumentEngineKey();
    $file_phid = $file->getPHID();

    return "/file/document/{$engine_key}/{$file_phid}/";
  }

  final public static function getEnginesForRef(
    PhabricatorUser $viewer,
    PhabricatorDocumentRef $ref) {
    $engines = self::getAllEngines();

    foreach ($engines as $key => $engine) {
      $engine = id(clone $engine)
        ->setViewer($viewer);

      if (!$engine->canRenderDocument($ref)) {
        unset($engines[$key]);
        continue;
      }

      $engines[$key] = $engine;
    }

    if (!$engines) {
      throw new Exception(pht('No content engine can render this document.'));
    }

    $vectors = array();
    foreach ($engines as $key => $usable_engine) {
      $vectors[$key] = $usable_engine->newSortVector($ref);
    }
    $vectors = msortv($vectors, 'getSelf');

    return array_select_keys($engines, array_keys($vectors));
  }

}
