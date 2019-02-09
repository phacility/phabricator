<?php

final class PhabricatorSpacesNamespaceTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'spaces';
  }

  public function getApplicationTransactionType() {
    return PhabricatorSpacesNamespacePHIDType::TYPECONST;
  }

  public function getBaseTransactionClass() {
    return 'PhabricatorSpacesNamespaceTransactionType';
  }

}
