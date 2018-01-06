<?php

final class DrydockBlueprintTransaction
  extends PhabricatorModularTransaction {

  const TYPE_NAME = 'drydock:blueprint:name';
  const TYPE_DISABLED = 'drydock:blueprint:disabled';

  public function getApplicationName() {
    return 'drydock';
  }

  public function getApplicationTransactionType() {
    return DrydockBlueprintPHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'DrydockBlueprintTransactionType';
  }

}
