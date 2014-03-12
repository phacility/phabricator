<?php

final class PhabricatorCommitBranchesField
  extends PhabricatorCommitCustomField {

  public function getFieldKey() {
    return 'diffusion:branches';
  }

  public function shouldAppearInApplicationTransactions() {
    return true;
  }

  public function buildApplicationTransactionMailBody(
    PhabricatorApplicationTransaction $xaction,
    PhabricatorMetaMTAMailBody $body) {

    $params = array(
      'contains' => $this->getObject()->getCommitIdentifier(),
      'callsign' => $this->getObject()->getRepository()->getCallsign(),
    );

    $branches_raw = id(new ConduitCall('diffusion.branchquery', $params))
      ->setUser($this->getViewer())
      ->execute();

    $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches_raw);
    if (!$branches) {
      return;
    }
    $branch_names = mpull($branches, 'getShortName');
    sort($branch_names);

    $body->addTextSection(pht('BRANCHES'), implode(', ', $branch_names));
  }

}
