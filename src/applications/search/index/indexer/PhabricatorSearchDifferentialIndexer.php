<?php

/**
 * @group search
 */
final class PhabricatorSearchDifferentialIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexRevision(DifferentialRevision $rev) {
    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($rev->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_DREV);
    $doc->setDocumentTitle($rev->getTitle());
    $doc->setDocumentCreated($rev->getDateCreated());
    $doc->setDocumentModified($rev->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $rev->getSummary());
    $doc->addField(
      PhabricatorSearchField::FIELD_TEST_PLAN,
      $rev->getTestPlan());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $rev->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $rev->getDateCreated());

    if ($rev->getStatus() != ArcanistDifferentialRevisionStatus::CLOSED &&
        $rev->getStatus() != ArcanistDifferentialRevisionStatus::ABANDONED) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $rev->getPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_DREV,
        time());
    }

    $comments = $rev->loadRelatives(new DifferentialComment(), 'revisionID');

    $inlines = $rev->loadRelatives(
      new DifferentialInlineComment(),
      'revisionID',
      'getID',
      '(commentID IS NOT NULL)');

    $touches = array();

    foreach (array_merge($comments, $inlines) as $comment) {
      if (strlen($comment->getContent())) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $comment->getContent());
      }

      $author = $comment->getAuthorPHID();
      $touches[$author] = $comment->getDateCreated();
    }

    foreach ($touches as $touch => $time) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_TOUCH,
        $touch,
        PhabricatorPHIDConstants::PHID_TYPE_USER,
        $time);
    }

    $rev->loadRelationships();

    // If a revision needs review, the owners are the reviewers. Otherwise, the
    // owner is the author (e.g., accepted, rejected, closed).
    if ($rev->getStatus() == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      foreach ($rev->getReviewers() as $phid) {
        $doc->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
          $phid,
          PhabricatorPHIDConstants::PHID_TYPE_USER,
          $rev->getDateModified()); // Bogus timestamp.
      }
    } else {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $rev->getAuthorPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_USER,
        $rev->getDateCreated());
    }

    $ccphids = $rev->getCCPHIDs();
    $handles = id(new PhabricatorObjectHandleData($ccphids))
      ->loadHandles();

    foreach ($handles as $phid => $handle) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $phid,
        $handle->getType(),
        $rev->getDateModified()); // Bogus timestamp.
    }

    self::reindexAbstractDocument($doc);
  }
}
