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