<?php

/**
 * @group differential
 */
final class DifferentialSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new DifferentialRevision();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $rev = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($rev->getPHID());
    $doc->setDocumentType(DifferentialPHIDTypeRevision::TYPECONST);
    $doc->setDocumentTitle($rev->getTitle());
    $doc->setDocumentCreated($rev->getDateCreated());
    $doc->setDocumentModified($rev->getDateModified());

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();
    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setUser(PhabricatorUser::getOmnipotentUser());
      if (!$aux_field->shouldAddToSearchIndex()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = DifferentialAuxiliaryField::loadFromStorage(
      $rev,
      $aux_fields);
    foreach ($aux_fields as $aux_field) {
      $doc->addField(
        $aux_field->getKeyForSearchIndex(),
        $aux_field->getValueForSearchIndex());
    }

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $rev->getAuthorPHID(),
      PhabricatorPeoplePHIDTypeUser::TYPECONST,
      $rev->getDateCreated());

    if ($rev->getStatus() != ArcanistDifferentialRevisionStatus::CLOSED &&
        $rev->getStatus() != ArcanistDifferentialRevisionStatus::ABANDONED) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $rev->getPHID(),
        DifferentialPHIDTypeRevision::TYPECONST,
        time());
    }

    $comments = id(new DifferentialCommentQuery())
      ->withRevisionIDs(array($rev->getID()))
      ->execute();

    $inlines = id(new DifferentialInlineCommentQuery())
      ->withRevisionIDs(array($rev->getID()))
      ->withNotDraft(true)
      ->execute();

    foreach (array_merge($comments, $inlines) as $comment) {
      if (strlen($comment->getContent())) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $comment->getContent());
      }
    }

    $rev->loadRelationships();

    // If a revision needs review, the owners are the reviewers. Otherwise, the
    // owner is the author (e.g., accepted, rejected, closed).
    if ($rev->getStatus() == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      foreach ($rev->getReviewers() as $phid) {
        $doc->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
          $phid,
          PhabricatorPeoplePHIDTypeUser::TYPECONST,
          $rev->getDateModified()); // Bogus timestamp.
      }
    } else {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $rev->getAuthorPHID(),
        PhabricatorPeoplePHIDTypeUser::TYPECONST,
        $rev->getDateCreated());
    }

    $ccphids = $rev->getCCPHIDs();
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($ccphids)
      ->execute();

    foreach ($handles as $phid => $handle) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $phid,
        $handle->getType(),
        $rev->getDateModified()); // Bogus timestamp.
    }

    return $doc;
  }
}
