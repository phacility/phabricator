<?php

/**
 * @deprecated
 */
final class PhabricatorOwnersPackagePrimaryTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.primary';

  public function shouldHide() {
    return true;
  }

}
