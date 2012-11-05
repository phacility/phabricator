<?php

final class PhabricatorSearchPonderIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexQuestion(PonderQuestion $question) {
    // note: we assume someone's already called attachrelated on $question

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
        $curcomment->getContent()
      );
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
          $curcomment->getContent()
        );
      }
    }

    self::reindexAbstractDocument($doc);
  }
}
