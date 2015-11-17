<?php

final class PhabricatorEditEngineConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'editengine.config.name';
  const TYPE_PREAMBLE = 'editengine.config.preamble';
  const TYPE_ORDER = 'editengine.config.order';

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
