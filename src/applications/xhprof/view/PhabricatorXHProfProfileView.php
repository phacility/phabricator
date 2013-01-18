<?php

abstract class PhabricatorXHProfProfileView extends AphrontView {

  private $baseURI;
  private $isFramed;

  public function setIsFramed($is_framed) {
    $this->isFramed = $is_framed;
    return $this;
  }

  public function setBaseURI($uri) {
    $this->baseURI = $uri;
    return $this;
  }

  protected function renderSymbolLink($symbol) {
    return phutil_tag(
      'a',
      array(
        'href'    => $this->baseURI.'?symbol='.$symbol,
        'target'  => $this->isFramed ? '_top' : null,
      ),
      $symbol);
  }

}
