<?php

final class PhamePostFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $post = $object;

    $document->setDocumentTitle($post->getTitle());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $post->getBody());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $post->getBloggerPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $post->getDateCreated());

    $document->addRelationship(
      $post->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $post->getPHID(),
      PhabricatorPhamePostPHIDType::TYPECONST,
      PhabricatorTime::getNow());

  }

}
