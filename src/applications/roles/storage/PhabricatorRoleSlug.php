<?php

final class PhabricatorRoleSlug extends PhabricatorRoleDAO {

  protected $slug;
  protected $rolePHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'slug' => 'text128',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_slug' => array(
          'columns' => array('slug'),
          'unique' => true,
        ),
        'key_rolePHID' => array(
          'columns' => array('rolePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
