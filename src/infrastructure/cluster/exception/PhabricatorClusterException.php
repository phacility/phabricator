<?php

abstract class PhabricatorClusterException
  extends Exception {

  abstract public function getExceptionTitle();

}
