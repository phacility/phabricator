<?php

final class PhabricatorApplicationEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorApplicationsApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Application');
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array();
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return array();
  }

}
