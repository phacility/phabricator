<?php

final class PhabricatorCommitRepositoryField
  extends PhabricatorCommitCustomField {

  public function getFieldKey() {
    return 'diffusion:repository';
  }

  public function shouldDisableByDefault() {
    return true;
  }

  public function shouldAppearInTransactionMail() {
    return true;
  }

  public function updateTransactionMailBody(
    PhabricatorMetaMTAMailBody $body,
    PhabricatorApplicationTransactionEditor $editor,
    array $xactions) {

    $repository = $this->getObject()->getRepository();

    $body->addTextSection(
      pht('REPOSITORY'),
      $repository->getMonogram().' '.$repository->getName());
  }

}
