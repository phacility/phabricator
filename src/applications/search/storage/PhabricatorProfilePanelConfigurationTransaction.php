<?php

final class PhabricatorProfilePanelConfigurationTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_PROPERTY = 'profilepanel.property';
  const TYPE_ORDER = 'profilepanel.order';
  const TYPE_VISIBILITY = 'profilepanel.visibility';

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
