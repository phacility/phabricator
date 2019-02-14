<?php

final class PhabricatorUserPreferencesTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_SETTING = 'setting';

  const PROPERTY_SETTING = 'setting.key';

  public function getApplicationName() {
    return 'user';
  }

  public function getApplicationTransactionType() {
    return PhabricatorUserPreferencesPHIDType::TYPECONST;
  }

}
