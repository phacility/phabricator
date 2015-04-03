<?php

abstract class ManiphestEmailCommand
  extends MetaMTAEmailTransactionCommand {

  public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof ManiphestTask);
  }

}
