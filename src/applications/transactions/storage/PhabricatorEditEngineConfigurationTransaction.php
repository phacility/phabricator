<?php

final class PhabricatorEditEngineConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'editengine.config.name';

  public function getApplicationName() {
    return 'search';
  }

  public function getApplicationTransactionType() {
    return PhabricatorEditEngineConfigurationPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}
