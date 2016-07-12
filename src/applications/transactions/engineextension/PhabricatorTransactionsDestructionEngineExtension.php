<?php

final class PhabricatorTransactionsDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'transactions';

  public function getExtensionName() {
    return pht('Transactions');
  }

  public function canDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return ($object instanceof PhabricatorApplicationTransactionInterface);
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $template = $object->getApplicationTransactionTemplate();
    $xactions = $template->loadAllWhere(
      'objectPHID = %s',
      $object->getPHID());
    foreach ($xactions as $xaction) {
      $engine->destroyObject($xaction);
    }
  }

}
