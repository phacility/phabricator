<?php

final class FundBackingEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorFundApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Fund Backing');
  }
}
