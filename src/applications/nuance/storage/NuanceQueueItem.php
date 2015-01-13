<?php

final class NuanceQueueItem
  extends NuanceDAO {

  protected $queuePHID;
  protected $itemPHID;
  protected $itemStatus;
  protected $itemDateNuanced;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'itemStatus' => 'uint32',
        'itemDateNuanced' => 'epoch',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_one_per_queue' => array(
          'columns' => array('itemPHID', 'queuePHID'),
          'unique' => true,
        ),
        'key_queue' => array(
          'columns' => array(
            'queuePHID',
            'itemStatus',
            'itemDateNuanced',
            'id',
          ),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
