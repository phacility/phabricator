<?php

final class PhabricatorUserSSHKey extends PhabricatorUserDAO {

  protected $userPHID;
  protected $name;
  protected $keyType;
  protected $keyBody;
  protected $keyHash;
  protected $keyComment;

  public function getEntireKey() {
    $parts = array(
      $this->getKeyType(),
      $this->getKeyBody(),
      $this->getKeyComment(),
    );
    return trim(implode(' ', $parts));
  }

}
