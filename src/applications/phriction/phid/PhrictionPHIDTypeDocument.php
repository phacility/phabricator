<?php

final class PhrictionPHIDTypeDocument extends PhabricatorPHIDType {

  const TYPECONST = 'WIKI';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Wiki Document');
  }

  public function getPHIDTypeApplicationClass() {
    return 'PhabricatorApplicationPhriction';
  }

  public function newObject() {
    return new PhrictionDocument();
  }

  protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhrictionDocumentQuery())
      ->withPHIDs($phids)
      ->needContent(true);
  }

  public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects) {

    foreach ($handles as $phid => $handle) {
      $document = $objects[$phid];
      $content = $document->getContent();

      $title = $content->getTitle();
      $slug = $document->getSlug();
      $status = $document->getStatus();

      $handle->setName($title);
      $handle->setURI(PhrictionDocument::getSlugURI($slug));

      if ($status != PhrictionDocumentStatus::STATUS_EXISTS) {
        $handle->setStatus(PhabricatorObjectHandleStatus::STATUS_CLOSED);
      }
    }
  }

}
