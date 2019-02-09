<?php

abstract class AlmanacModularTransaction
  extends PhabricatorModularTransaction {

  public function getApplicationName() {
    return 'almanac';
  }

}
