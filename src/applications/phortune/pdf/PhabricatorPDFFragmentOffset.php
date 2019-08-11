<?php

final class PhabricatorPDFFragmentOffset
  extends Phobject {

  private $fragment;
  private $offset;

  public function setFragment(PhabricatorPDFFragment $fragment) {
    $this->fragment = $fragment;
    return $this;
  }

  public function getFragment() {
    return $this->fragment;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function getOffset() {
    return $this->offset;
  }

}
