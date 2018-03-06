<?php

final class DifferentialRevisionUpdateTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential:update';
  const EDITKEY = 'update';

  public function generateOldValue($object) {
    return $object->getActiveDiffPHID();
  }

  public function applyInternalEffects($object, $value) {
    $should_review = $this->shouldRequestReviewAfterUpdate($object);
    if ($should_review) {
      $object->setModernRevisionStatus(
        DifferentialRevisionStatus::NEEDS_REVIEW);
    }

    $editor = $this->getEditor();
    $diff = $editor->requireDiff($value);

    $this->updateRevisionLineCounts($object, $diff);

    $object->setRepositoryPHID($diff->getRepositoryPHID());
    $object->setActiveDiffPHID($diff->getPHID());
    $object->attachActiveDiff($diff);
  }

  private function shouldRequestReviewAfterUpdate($object) {
    if ($this->isCommitUpdate()) {
      return false;
    }

    $should_update =
      $object->isNeedsRevision() ||
      $object->isChangePlanned() ||
      $object->isAbandoned();
    if ($should_update) {
      return true;
    }

    return false;
  }

  public function applyExternalEffects($object, $value) {
    $editor = $this->getEditor();
    $diff = $editor->requireDiff($value);

    // TODO: This can race with diff updates, particularly those from
    // Harbormaster. See discussion in T8650.
    $diff->setRevisionID($object->getID());
    $diff->save();

    // If there are any outstanding buildables for this diff, tell
    // Harbormaster that their containers need to be updated. This is
    // common, because `arc` creates buildables so it can upload lint
    // and unit results.

    $buildables = id(new HarbormasterBuildableQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withManualBuildables(false)
      ->withBuildablePHIDs(array($diff->getPHID()))
      ->execute();
    foreach ($buildables as $buildable) {
      $buildable->sendMessage(
        $this->getActor(),
        HarbormasterMessageType::BUILDABLE_CONTAINER,
        true);
    }
  }

  public function getColor() {
    return 'sky';
  }

  public function getIcon() {
    return 'fa-refresh';
  }

  public function getActionName() {
    if ($this->isCreateTransaction()) {
      return pht('Request');
    } else {
      return pht('Updated');
    }
  }

  public function getActionStrength() {
    return 2;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($this->isCommitUpdate()) {
      return pht(
        'This revision was automatically updated to reflect the '.
        'committed changes.');
    }

    // NOTE: Very, very old update transactions did not have a new value or
    // did not use a diff PHID as a new value. This was changed years ago,
    // but wasn't migrated. We might consider migrating if this causes issues.

    return pht(
      '%s updated this revision to %s.',
      $this->renderAuthor(),
      $this->renderNewHandle());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the diff for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $diff_phid = null;
    foreach ($xactions as $xaction) {
      $diff_phid = $xaction->getNewValue();

      $diff = id(new DifferentialDiffQuery())
        ->withPHIDs(array($diff_phid))
        ->setViewer($this->getActor())
        ->executeOne();
      if (!$diff) {
        $errors[] = $this->newInvalidError(
          pht(
            'Specified diff ("%s") does not exist.',
            $diff_phid),
          $xaction);
        continue;
      }

      if ($diff->getRevisionID()) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not update this revision with the specified diff ("%s") '.
            'because the diff is already attached to another revision.',
            $diff_phid),
          $xaction);
        continue;
      }
    }

    if (!$diff_phid && !$object->getActiveDiffPHID()) {
      $errors[] = $this->newInvalidError(
        pht(
          'You must specify an initial diff when creating a revision.'));
    }

    return $errors;
  }

  public function isCommitUpdate() {
    return (bool)$this->getMetadataValue('isCommitUpdate');
  }

  private function updateRevisionLineCounts(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {

    $revision->setLineCount($diff->getLineCount());

    $conn = $revision->establishConnection('r');

    $row = queryfx_one(
      $conn,
      'SELECT SUM(addLines) A, SUM(delLines) D FROM %T
        WHERE diffID = %d',
      id(new DifferentialChangeset())->getTableName(),
      $diff->getID());

    if ($row) {
      $revision->setAddedLineCount((int)$row['A']);
      $revision->setRemovedLineCount((int)$row['D']);
    }
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'update';
  }

  public function getFieldValuesForConduit($object, $data) {
    $commit_phids = $object->getMetadataValue('commitPHIDs', array());

    return array(
      'old' => $object->getOldValue(),
      'new' => $object->getNewValue(),
      'commitPHIDs' => $commit_phids,
    );
  }

}
