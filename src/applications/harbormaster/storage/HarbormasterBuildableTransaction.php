<?php

final class HarbormasterBuildableTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterBuildablePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'HarbormasterBuildableTransactionType';
  }

}
