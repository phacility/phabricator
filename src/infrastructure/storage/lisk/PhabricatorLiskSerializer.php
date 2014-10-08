<?php

abstract class PhabricatorLiskSerializer {

  abstract public function willReadValue($value);
  abstract public function willWriteValue($value);

}
