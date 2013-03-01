<?php

final class PonderSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PonderQuestion();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $question = $this->loadDocumentByPHID($phid);

    $question->attachRelated();

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($question->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_QUES);
    $doc->setDocumentTitle($question->getTitle());
    $doc->setDocumentCreated($question->getDateCreated());
    $doc->setDocumentModified($question->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $question->getContent());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $question->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $question->getDateCreated());

    $comments = $question->getComments();
    foreach ($comments as $curcomment) {
      $doc->addField(
        PhabricatorSearchField::FIELD_COMMENT,
        $curcomment->getContent());
    }

    $answers = $question->getAnswers();
    foreach ($answers as $curanswer) {
      if (strlen($curanswer->getContent())) {
          $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $curanswer->getContent());
      }

      $answer_comments = $curanswer->getComments();
      foreach ($answer_comments as $curcomment) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $curcomment->getContent());
      }
    }

    $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $question->getPHID());
    $handles = id(new PhabricatorObjectHandleData($subscribers))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->loadHandles();

    foreach ($handles as $phid => $handle) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $phid,
        $handle->getType(),
        $question->getDateModified()); // Bogus timestamp.
    }

    return $doc;
  }
}
