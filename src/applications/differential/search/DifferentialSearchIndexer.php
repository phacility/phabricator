<?php

final class DifferentialSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new DifferentialRevision();
  }

  protected function loadDocumentByPHID($phid) {
    $object = id(new DifferentialRevisionQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->needReviewerStatus(true)
      ->executeOne();
    if (!$object) {
      throw new Exception(pht("Unable to load object by PHID '%s'!", $phid));
    }
    return $object;
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $rev = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($rev->getPHID());
    $doc->setDocumentType(DifferentialRevisionPHIDType::TYPECONST);
    $doc->setDocumentTitle($rev->getTitle());
    $doc->setDocumentCreated($rev->getDateCreated());
    $doc->setDocumentModified($rev->getDateModified());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $rev->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $rev->getDateCreated());

    $doc->addRelationship(
      $rev->isClosed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $rev->getPHID(),
      DifferentialRevisionPHIDType::TYPECONST,
      time());

    $this->indexTransactions(
      $doc,
      new DifferentialTransactionQuery(),
      array($rev->getPHID()));

    // If a revision needs review, the owners are the reviewers. Otherwise, the
    // owner is the author (e.g., accepted, rejected, closed).
    if ($rev->getStatus() == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      $reviewers = $rev->getReviewerStatus();
      $reviewers = mpull($reviewers, 'getReviewerPHID', 'getReviewerPHID');
      if ($reviewers) {
        foreach ($reviewers as $phid) {
          $doc->addRelationship(
            PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
            $phid,
            PhabricatorPeopleUserPHIDType::TYPECONST,
            $rev->getDateModified()); // Bogus timestamp.
        }
      } else {
        $doc->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED,
          $rev->getPHID(),
          PhabricatorPeopleUserPHIDType::TYPECONST,
          $rev->getDateModified()); // Bogus timestamp.
      }
    } else {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $rev->getAuthorPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_VOID,
        $rev->getDateCreated());
    }

    return $doc;
  }
}
