<?php

final class PhabricatorConpherenceRoomTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'conpherence';

  public function getGeneratorName() {
    return pht('Conpherence');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();

    $name = $this->newRoomName();

    $participants = array();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();
    $participants[] = $this->loadRandomUser();

    $rando_phids = array();
    $rando_phids[] = $author->getPHID();
    foreach ($participants as $actor) {
      $rando_phids[] = $actor->getPHID();
    }

    $xactions = array();

    $xactions[] = array(
      'type' => 'name',
      'value' => $name,
    );

    $xactions[] = array(
      'type' => 'participants.set',
      'value' => $rando_phids,
    );

    $xactions[] = array(
      'type' => 'view',
      'value' => 'users',
    );

    $xactions[] = array(
      'type' => 'edit',
      'value' => 'users',
    );

    $params = array(
      'transactions' => $xactions,
    );

    $result = id(new ConduitCall('conpherence.edit', $params))
      ->setUser($author)
      ->execute();

    return $result['object']['phid'];
  }

  protected function newRoomName() {
    $generator = new PhabricatorConpherenceRoomContextFreeGrammar();
    $name = $generator->generate();
    return $name;
  }



}
