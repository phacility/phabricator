<?php

/**
 * @group maniphest
 */
final class ManiphestSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new ManiphestTask();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $task = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($task->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_TASK);
    $doc->setDocumentTitle($task->getTitle());
    $doc->setDocumentCreated($task->getDateCreated());
    $doc->setDocumentModified($task->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $task->getDescription());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $task->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $task->getDateCreated());

    if ($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $task->getPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_TASK,
        time());
    }

    $transactions = id(new ManiphestTransaction())->loadAllWhere(
      'taskID = %d',
      $task->getID());

    $current_ccs = $task->getCCPHIDs();
    $touches = array();
    $owner = null;
    $ccs = array();
    foreach ($transactions as $transaction) {
      if ($transaction->hasComments()) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $transaction->getComments());
      }

      $author = $transaction->getAuthorPHID();

      // Record the most recent time they touched this object.
      $touches[$author] = $transaction->getDateCreated();

      switch ($transaction->getTransactionType()) {
        case ManiphestTransactionType::TYPE_OWNER:
          $owner = $transaction;
          break;
        case ManiphestTransactionType::TYPE_CCS:
          // For users who are still CC'd, record the first time they were
          // added to CC.
          foreach ($transaction->getNewValue() as $added_cc) {
            if (in_array($added_cc, $current_ccs)) {
              if (empty($ccs[$added_cc])) {
                $ccs[$added_cc] = $transaction->getDateCreated();
              }
            }
          }
          break;
      }
    }

    foreach ($task->getProjectPHIDs() as $phid) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_PROJECT,
        $phid,
        PhabricatorPHIDConstants::PHID_TYPE_PROJ,
        $task->getDateModified()); // Bogus.
    }

    if ($owner && $owner->getNewValue()) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $owner->getNewValue(),
        PhabricatorPHIDConstants::PHID_TYPE_USER,
        $owner->getDateCreated());
    } else {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        ManiphestTaskOwner::OWNER_UP_FOR_GRABS,
        PhabricatorPHIDConstants::PHID_TYPE_MAGIC,
        $owner
          ? $owner->getDateCreated()
          : $task->getDateCreated());
    }

    foreach ($touches as $touch => $time) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_TOUCH,
        $touch,
        PhabricatorPHIDConstants::PHID_TYPE_USER,
        $time);
    }

    // We need to load handles here since non-users may subscribe (mailing
    // lists, e.g.)
    $handles = id(new PhabricatorObjectHandleData(array_keys($ccs)))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->loadHandles();
    foreach ($ccs as $cc => $time) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $handles[$cc]->getPHID(),
        $handles[$cc]->getType(),
        $time);
    }

    return $doc;
  }
}
