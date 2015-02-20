<?php

final class HeraldRuleEdit extends HeraldDAO {

  protected $editorPHID;
  protected $ruleID;
  protected $ruleName;
  protected $action;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'ruleName' => 'text255',
        'action' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'ruleID' => array(
          'columns' => array('ruleID', 'dateCreated'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
