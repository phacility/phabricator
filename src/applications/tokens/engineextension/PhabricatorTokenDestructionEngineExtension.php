<?php

final class PhabricatorTokenDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'tokens';

  public function getExtensionName() {
    return pht('Tokens');
  }

  public function canDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return ($object instanceof PhabricatorTokenReceiverInterface);
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    $tokens = id(new PhabricatorTokenGiven())->loadAllWhere(
      'objectPHID = %s',
      $object->getPHID());

    foreach ($tokens as $token) {
      $token->delete();
    }
  }

}
