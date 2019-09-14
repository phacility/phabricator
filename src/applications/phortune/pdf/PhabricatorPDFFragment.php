<?php

abstract class PhabricatorPDFFragment
  extends Phobject {

  private $rope;

  public function getAsBytes() {
    $this->rope = new PhutilRope();

    $this->writeFragment();

    $rope = $this->rope;
    $this->rope = null;

    return $rope->getAsString();
  }

  public function hasRefTableEntry() {
    return false;
  }

  abstract protected function writeFragment();

  final protected function writeLine($pattern) {
    $pattern = $pattern."\n";

    $argv = func_get_args();
    $argv[0] = $pattern;

    $line = call_user_func_array('sprintf', $argv);

    $this->rope->append($line);

    return $this;
  }

}
