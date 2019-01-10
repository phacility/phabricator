<?php

final class PhabricatorUserTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Users');
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
