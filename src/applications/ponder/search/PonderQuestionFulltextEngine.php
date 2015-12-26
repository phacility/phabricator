<?php

final class PonderQuestionFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $question = $object;

    $document->setDocumentTitle($question->getTitle());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $question->getContent());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $question->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $question->getDateCreated());
  }
}
