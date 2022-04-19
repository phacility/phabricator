<?php


class ResolveCodeChange {
  public TransactionList $transactions;
  public DifferentialRevision $rawRevision;
  public PhabricatorDiffStore $diffStore;

  public function __construct(TransactionList $transactions, DifferentialRevision $rawRevision, PhabricatorDiffStore $diffStore) {
    $this->transactions = $transactions;
    $this->rawRevision = $rawRevision;
    $this->diffStore = $diffStore;
  }

  public function resolveAnyDiffChanges(): bool {
    if ($this->transactions->attemptGetTransactionWithType('differential.revision.summary')) {
      // The commit message/summary/bug/etc change
      return true;
    }

    $updateTx = $this->transactions->getTransactionWithType('differential:update');
    $oldDiff = $this->diffStore->find($updateTx->getOldValue());
    $newDiff = $this->diffStore->find($updateTx->getNewValue());
    $oldChangesets = $oldDiff->getChangesets();
    $newChangesets = $newDiff->getChangesets();

    if (count($oldChangesets) != count($newChangesets)) {
      return true;
    }

    foreach (array_map(null, $oldChangesets, $newChangesets) as $changesetPair) {
      [$oldChangeset, $newChangeset] = $changesetPair;

      if ($oldChangeset->getFileType() != $newChangeset->getFileType()
        || $oldChangeset->getChangeType() != $newChangeset->getChangeType()) {
        return true;
      }

      if ($newChangeset->getFileType() != DifferentialChangeType::FILE_TEXT
        || $newChangeset->getChangeType() != DifferentialChangeType::TYPE_CHANGE) {
        // For all changes other than "text file updated", fall back to the
        // Phabricator-native way of checking if the changesets are the same
        if (!$newChangeset->hasSameEffectAs($oldChangeset)) {
          return true;
        }
      }

      // This an update to a text file. Let's only consider this changeset
      // to have been "changed" if its individual modifications are different
      // from the previous changeset.

      $oldHunks = $oldChangeset->getHunks();
      $newHunks = $newChangeset->getHunks();

      if (count($oldHunks) != count($newHunks)) {
        return true;
      }

      foreach (array_map(null, $oldHunks, $newHunks) as $hunkPair) {
        [$oldHunk, $newHunk] = $hunkPair;
        $oldLines = explode("\n", $oldHunk->getData());
        $newLines = explode("\n", $newHunk->getData());

        if (count($oldLines) != count($newLines)) {
          return true;
        }

        foreach (array_map(null, $oldLines, $newLines) as $linePair) {
          [$oldLine, $newLine] = $linePair;

          if (($oldLine !== $newLine) && ((strlen($oldLine) && $oldLine[0] != " ") || (strlen($newLine) && $newLine[0] != " "))) {
            // If the lines aren't equivalent, and one of the lines starts with
            // a non-space (so, either a "+" or "-" for added/removed contents)
            return true;
          }
        }
      }
    }
    return false;
  }

  /**
   * @return EmailAffectedFile[]
   */
  public function resolveAffectedFiles(): array {
    $diff = $this->diffStore->find($this->rawRevision->getActiveDiffPHID());
    $changesets = $diff->getChangesets();
    $affectedFiles = [];
    foreach($changesets as $changeset) {
      $affectedFiles[] = EmailAffectedFile::from($changeset);
    }
    return $affectedFiles;
  }

  public function resolveNewChangesLink(): string {
    $updateTx = $this->transactions->getTransactionWithType('differential:update');
    $original = $this->diffStore->find($updateTx->getOldValue());
    $oldId = $original->getID();
    $current = $this->rawRevision->getActiveDiff();
    $newId = $current->getID();
    return PhabricatorEnv::getProductionURI('/'.$this->rawRevision->getMonogram().'?vs='.$oldId.'&id='.$newId);
  }
}
