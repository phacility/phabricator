<?php

final class PhabricatorSystemActionLog extends PhabricatorSystemDAO {

  protected $actorHash;
  protected $actorIdentity;
  protected $action;
  protected $score;
  protected $epoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'actorHash' => 'bytes12',
        'actorIdentity' => 'text255',
        'action' => 'text32',
        'score' => 'double',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
        'key_action' => array(
          'columns' => array('actorHash', 'action', 'epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function setActorIdentity($identity) {
    $this->setActorHash(PhabricatorHash::digestForIndex($identity));
    return parent::setActorIdentity($identity);
  }

}
