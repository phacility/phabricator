<?php

final class PhabricatorSystemActionLog extends PhabricatorSystemDAO {

  protected $actorHash;
  protected $actorIdentity;
  protected $action;
  protected $score;
  protected $epoch;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function setActorIdentity($identity) {
    $this->setActorHash(PhabricatorHash::digestForIndex($identity));
    return parent::setActorIdentity($identity);
  }

}
