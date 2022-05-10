<?php

final class PhabricatorFileAttachment
  extends PhabricatorFileDAO {

  protected $objectPHID;
  protected $filePHID;
  protected $attacherPHID;
  protected $attachmentMode;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectPHID' => 'phid',
        'filePHID' => 'phid',
        'attacherPHID' => 'phid?',
        'attachmentMode' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_object' => array(
          'columns' => array('objectPHID', 'filePHID'),
          'unique' => true,
        ),
        'key_file' => array(
          'columns' => array('filePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
