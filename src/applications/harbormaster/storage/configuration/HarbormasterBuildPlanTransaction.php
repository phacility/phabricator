<?php

final class HarbormasterBuildPlanTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterBuildPlanPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'HarbormasterBuildPlanTransactionType';
  }

}
