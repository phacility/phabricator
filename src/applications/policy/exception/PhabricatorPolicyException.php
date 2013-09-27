<?php

final class PhabricatorPolicyException extends Exception {

  private $moreInfo = array();

  public function setMoreInfo(array $more_info) {
    $this->moreInfo = $more_info;
    return $this;
  }

  public function getMoreInfo() {
    return $this->moreInfo;
  }

}
