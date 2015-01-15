<?php

final class PhabricatorProjectSlug extends PhabricatorProjectDAO {

  protected $slug;
  protected $projectPHID;

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
        'key_projectPHID' => array(
          'columns' => array('projectPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
