<?php

final class HeraldActionRecord extends HeraldDAO {

  protected $ruleID;

  protected $action;
  protected $target;

  public function getTableName() {
    // TODO: This class was renamed, but we have a migration which affects the
    // table prior to to the rename. For now, having cruft here is cleaner than
    // having it in the migration. We could rename this table again and no-op
    // the migration after some time. See T8958.
    return 'herald_action';
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'target' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'action' => 'text255',
        'target' => 'text',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'ruleID' => array(
          'columns' => array('ruleID'),
        ),
      ),
    ) + parent::getConfiguration();
  }


}
