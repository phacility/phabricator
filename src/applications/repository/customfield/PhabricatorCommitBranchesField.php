<?php

final class PhabricatorCommitBranchesField
  extends PhabricatorCommitCustomField {

  public function getFieldKey() {
    return 'diffusion:branches';
  }

  public function getFieldName() {
    return pht('Branches');
  }

  public function getFieldDescription() {
    return pht('Shows branches a commit appears on in email.');
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    $params = array(
      'contains' => $this->getObject()->getCommitIdentifier(),
      'repository' => $this->getObject()->getRepository()->getPHID(),
    );

    try {
      $branches_raw = id(new ConduitCall('diffusion.branchquery', $params))
        ->setUser($this->getViewer())
        ->execute();

      $branches = DiffusionRepositoryRef::loadAllFromDictionaries(
        $branches_raw);
      if (!$branches) {
        return;
      }

      $branch_names = mpull($branches, 'getShortName');
      sort($branch_names);
      $branch_text = implode(', ', $branch_names);
    } catch (Exception $ex) {
      $branch_text = pht('<%s: %s>', get_class($ex), $ex->getMessage());
    }

    $body->addTextSection(pht('BRANCHES'), $branch_text);
  }

}
