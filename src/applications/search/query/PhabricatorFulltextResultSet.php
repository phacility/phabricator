<?php

final class PhabricatorFulltextResultSet extends Phobject {

  private $phids;
  private $fulltextTokens;

  public function setPHIDs($phids) {
    $this->phids = $phids;
    return $this;
  }

  public function getPHIDs() {
    return $this->phids;
  }

  public function setFulltextTokens($fulltext_tokens) {
    $this->fulltextTokens = $fulltext_tokens;
    return $this;
  }

  public function getFulltextTokens() {
    return $this->fulltextTokens;
  }

}
