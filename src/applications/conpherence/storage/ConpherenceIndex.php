<?php

final class ConpherenceIndex
  extends ConpherenceDAO {

  protected $threadPHID;
  protected $transactionPHID;
  protected $previousTransactionPHID;
  protected $corpus;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'previousTransactionPHID' => 'phid?',
        'corpus' => 'fulltext',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_thread' => array(
          'columns' => array('threadPHID'),
        ),
        'key_transaction' => array(
          'columns' => array('transactionPHID'),
          'unique' => true,
        ),
        'key_previous' => array(
          'columns' => array('previousTransactionPHID'),
          'unique' => true,
        ),
        'key_corpus' => array(
          'columns' => array('corpus'),
          'type' => 'FULLTEXT',
        ),
      ),
    ) + parent::getConfiguration();
  }

}
