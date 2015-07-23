<?php

abstract class HeraldCustomAction extends Phobject {

  abstract public function appliesToAdapter(HeraldAdapter $adapter);

  abstract public function appliesToRuleType($rule_type);

  abstract public function getActionKey();

  abstract public function getActionName();

  abstract public function getActionType();

  abstract public function applyEffect(
    HeraldAdapter $adapter,
    $object,
    HeraldEffect $effect);

}
