<?php

final class PassphraseCredentialFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $credential = $object;

    $document->setDocumentTitle($credential->getName());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $credential->getDescription());

    $document->addRelationship(
      $credential->getIsDestroyed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $credential->getPHID(),
      PassphraseCredentialPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
