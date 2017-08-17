<?php

final class DifferentialRevisionFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($object->getPHID()))
      ->needReviewers(true)
      ->executeOne();

    // TODO: This isn't very clean, but custom fields currently rely on it.
    $object->attachReviewers($revision->getReviewers());

    $document->setDocumentTitle($revision->getTitle());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $revision->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $revision->getDateCreated());

    $document->addRelationship(
      $revision->isClosed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $revision->getPHID(),
      DifferentialRevisionPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    // If a revision needs review, the owners are the reviewers. Otherwise, the
    // owner is the author (e.g., accepted, rejected, closed).
    if ($revision->isNeedsReview()) {
      $reviewers = $revision->getReviewerPHIDs();
      $reviewers = array_fuse($reviewers);

      if ($reviewers) {
        foreach ($reviewers as $phid) {
          $document->addRelationship(
            PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
            $phid,
            PhabricatorPeopleUserPHIDType::TYPECONST,
            $revision->getDateModified()); // Bogus timestamp.
        }
      } else {
        $document->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED,
          $revision->getPHID(),
          PhabricatorPeopleUserPHIDType::TYPECONST,
          $revision->getDateModified()); // Bogus timestamp.
      }
    } else {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $revision->getAuthorPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_VOID,
        $revision->getDateCreated());
    }
  }
}
