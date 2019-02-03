<?php

abstract class PhabricatorAuthFactorProviderTransactionType
  extends PhabricatorModularTransactionType {

  final protected function isDuoProvider(
    PhabricatorAuthFactorProvider $provider) {
    $duo_key = id(new PhabricatorDuoAuthFactor())->getFactorKey();
    return ($provider->getProviderFactorKey() === $duo_key);
  }

}
