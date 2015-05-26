<?php

abstract class PhabricatorCalendarEventEmailCommand
  extends MetaMTAEmailTransactionCommand {

  public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorCalendarEvent);
  }

}
