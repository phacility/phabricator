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
      self::CONFIG_COLUMN_SCHEMA => array(
        'actorHash' => 'bytes12',
        'actorIdentity' => 'text255',
        'action' => 'text32',
        'score' => 'double',
      ),
    ) + parent::getConfiguration();
  }

  public function setActorIdentity($identity) {
    $this->setActorHash(PhabricatorHash::digestForIndex($identity));
    return parent::setActorIdentity($identity);
  }

}
