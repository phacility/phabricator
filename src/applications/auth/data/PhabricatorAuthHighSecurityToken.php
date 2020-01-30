<?php

final class PhabricatorAuthHighSecurityToken
  extends Phobject {

  private $isUnchallengedToken = false;

  public function setIsUnchallengedToken($is_unchallenged_token) {
    $this->isUnchallengedToken = $is_unchallenged_token;
    return $this;
  }

  public function getIsUnchallengedToken() {
    return $this->isUnchallengedToken;
  }

}
