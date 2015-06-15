<?php

abstract class PhabricatorLiskSerializer extends Phobject {

  abstract public function willReadValue($value);
  abstract public function willWriteValue($value);

}
