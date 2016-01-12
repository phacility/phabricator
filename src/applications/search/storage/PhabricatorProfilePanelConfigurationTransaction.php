<?php

final class PhabricatorProfilePanelConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_PROPERTY = 'profilepanel.property';

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
