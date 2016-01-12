<?php

final class PhabricatorProfilePanelConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'search';
  }

  public function getApplicationTransactionType() {
    return PhabricatorProfilePanelPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

}
