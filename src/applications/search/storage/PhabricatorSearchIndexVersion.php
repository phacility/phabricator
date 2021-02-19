<?php

final class PhabricatorSearchIndexVersion
  extends PhabricatorSearchDAO {

  protected $objectPHID;
  protected $extensionKey;
  protected $version;
  protected $indexVersion;
  protected $indexEpoch;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'extensionKey' => 'text64',
        'version' => 'text128',
        'indexVersion' => 'bytes12',
        'indexEpoch' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID', 'extensionKey'),
          'unique' => true,
        ),

        // NOTE: "bin/search index" may query this table by "indexVersion" or
        // "indexEpoch", but this is rare and scanning the table seems fine.

      ),
    ) + parent::getConfiguration();
  }

}
