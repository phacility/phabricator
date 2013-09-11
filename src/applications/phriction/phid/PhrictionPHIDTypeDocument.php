<?php

final class PhrictionPHIDTypeDocument extends PhabricatorPHIDType {

  const TYPECONST = 'WIKI';

  public function getTypeConstant() {
    return self::TYPECONST;
  }

  public function getTypeName() {
    return pht('Wiki Document');
  }

  public function newObject() {
    return new PhrictionDocument();
  }

  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    return id(new PhrictionDocumentQuery())
      ->setViewer($query->getViewer())
      ->setParentQuery($query)
      ->withPHIDs($phids)
      ->execute();
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

  public function canLoadNamedObject($name) {
    return false;
  }

}
