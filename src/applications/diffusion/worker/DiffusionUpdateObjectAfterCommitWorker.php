<?php

final class DiffusionUpdateObjectAfterCommitWorker
  extends PhabricatorWorker {

  private $properties;

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function doWork() {
    $viewer = $this->getViewer();
    $data = $this->getTaskData();

    $commit_phid = idx($data, 'commitPHID');
    if (!$commit_phid) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No "commitPHID" in task data.'));
    }

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($commit_phid))
      ->needIdentities(true)
      ->executeOne();
    if (!$commit) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load commit "%s".',
          $commit_phid));
    }

    $object_phid = idx($data, 'objectPHID');
    if (!$object_phid) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No "objectPHID" in task data.'));
    }

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$object) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load object "%s".',
          $object_phid));
    }

    $properties = idx($data, 'properties', array());
    $this->properties = $properties;

    if ($object instanceof ManiphestTask) {
      $this->updateTask($commit, $object);
    } else if ($object instanceof DifferentialRevision) {
      $this->updateRevision($commit, $object);
    }
  }

  protected function getUpdateProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  protected function getActingPHID(PhabricatorRepositoryCommit $commit) {
    if ($commit->hasCommitterIdentity()) {
      return $commit->getCommitterIdentity()->getIdentityDisplayPHID();
    }

    if ($commit->hasAuthorIdentity()) {
      return $commit->getAuthorIdentity()->getIdentityDisplayPHID();
    }

    return id(new PhabricatorDiffusionApplication())->getPHID();
  }

  protected function loadActingUser($acting_phid) {
    // If we we were able to identify an author or committer for the commit, we
    // try to act as that user when affecting other objects, like tasks marked
    // with "Fixes Txxx".

    // This helps to prevent mistakes where a user accidentally writes the
    // wrong task IDs and affects tasks they can't see (and thus can't undo the
    // status changes for).

    // This is just a guard rail, not a security measure. An attacker can still
    // forge another user's identity trivially by forging author or committer
    // email addresses.

    // We also let commits with unrecognized authors act on any task to make
    // behavior less confusing for new installs, and any user can craft a
    // commit with an unrecognized author and committer.

    $viewer = $this->getViewer();

    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    if (phid_get_type($acting_phid) === $user_type) {
      $acting_user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($acting_phid))
        ->executeOne();
      if ($acting_user) {
        return $acting_user;
      }
    }

    return $viewer;
  }

  private function updateTask(
    PhabricatorRepositoryCommit $commit,
    ManiphestTask $task) {

    $acting_phid = $this->getActingPHID($commit);
    $acting_user = $this->loadActingUser($acting_phid);

    $commit_phid = $commit->getPHID();

    $xactions = array();

    $xactions[] = $this->newEdgeTransaction(
      $task,
      $commit,
      ManiphestTaskHasCommitEdgeType::EDGECONST);

    $status = $this->getUpdateProperty('status');
    if ($status) {
      $xactions[] = $task->getApplicationTransactionTemplate()
        ->setTransactionType(ManiphestTaskStatusTransaction::TRANSACTIONTYPE)
        ->setMetadataValue('commitPHID', $commit_phid)
        ->setNewValue($status);
    }

    $content_source = $this->newContentSource();

    $editor = $task->getApplicationTransactionEditor()
      ->setActor($acting_user)
      ->setActingAsPHID($acting_phid)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->addUnmentionablePHIDs(array($commit_phid));

    $editor->applyTransactions($task, $xactions);
  }

  private function updateRevision(
    PhabricatorRepositoryCommit $commit,
    DifferentialRevision $revision) {

    // Reload the revision to get the active diff, which is currently required
    // by "updateRevisionWithCommit()".
    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($revision->getID()))
      ->needActiveDiffs(true)
      ->executeOne();

    $acting_phid = $this->getActingPHID($commit);
    $acting_user = $this->loadActingUser($acting_phid);

    $xactions = array();

    $xactions[] = $this->newEdgeTransaction(
      $revision,
      $commit,
      DifferentialRevisionHasCommitEdgeType::EDGECONST);

    $match_data = $this->getUpdateProperty('revisionMatchData');

    $type_close = DifferentialRevisionCloseTransaction::TRANSACTIONTYPE;
    $xactions[] = $revision->getApplicationTransactionTemplate()
      ->setTransactionType($type_close)
      ->setNewValue(true)
      ->setMetadataValue('isCommitClose', true)
      ->setMetadataValue('revisionMatchData', $match_data)
      ->setMetadataValue('commitPHID', $commit->getPHID());

    $extraction_engine = id(new DifferentialDiffExtractionEngine())
      ->setViewer($acting_user)
      ->setAuthorPHID($acting_phid);

    $content_source = $this->newContentSource();

    $extraction_engine->updateRevisionWithCommit(
      $revision,
      $commit,
      $xactions,
      $content_source);
  }

  private function newEdgeTransaction(
    $object,
    PhabricatorRepositoryCommit $commit,
    $edge_type) {

    $commit_phid = $commit->getPHID();

    return $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edge_type)
      ->setNewValue(
        array(
          '+' => array(
            $commit_phid => $commit_phid,
          ),
        ));
  }


}
