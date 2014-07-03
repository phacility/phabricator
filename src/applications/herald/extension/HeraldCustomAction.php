<?php

abstract class HeraldCustomAction {

  public abstract function appliesToAdapter(HeraldAdapter $adapter);

  public abstract function appliesToRuleType($rule_type);

  public abstract function getActionKey();

  public abstract function getActionName();

  public abstract function getActionType();

  public abstract function applyEffect(
    HeraldAdapter $adapter,
    $object,
    HeraldEffect $effect);

}
