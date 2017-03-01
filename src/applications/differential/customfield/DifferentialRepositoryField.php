<?php

final class DifferentialRepositoryField
  extends DifferentialCoreCustomField {

  public function getFieldKey() {
    return 'differential:repository';
  }

  public function getFieldName() {
    return pht('Repository');
  }

  public function getFieldDescription() {
    return pht('Associates a revision with a repository.');
  }

  protected function readValueFromRevision(
    DifferentialRevision $revision) {
    return $revision->getRepositoryPHID();
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewValue(array $handles) {
    return null;
  }

  public function shouldAppearInDiffPropertyView() {
    return true;
  }

  public function renderDiffPropertyViewLabel(DifferentialDiff $diff) {
    return $this->getFieldName();
  }

  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {
    if (!$diff->getRepositoryPHID()) {
      return null;
    }

    return $this->getViewer()->renderHandle($diff->getRepositoryPHID());
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    $repository = $this->getObject()->getRepository();
    if ($repository === null) {
      return;
    }

    $body->addTextSection(
      pht('REPOSITORY'),
      $repository->getMonogram().' '.$repository->getName());
  }

}
