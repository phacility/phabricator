<?php

final class PholioTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $imageID;
  protected $x;
  protected $y;
  protected $width;
  protected $height;
  protected $content;

  public function getApplicationTransactionObject() {
    return new PholioTransaction();
  }

  protected function getConfiguration() {
    $config = parent::getConfiguration();

    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'imageID' => 'id?',
      'x' => 'uint32?',
      'y' => 'uint32?',
      'width' => 'uint32?',
      'height' => 'uint32?',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];

    $config[self::CONFIG_KEY_SCHEMA] = array(
      'key_draft' => array(
        'columns' => array('authorPHID', 'imageID', 'transactionPHID'),
        'unique' => true,
      ),
    ) + $config[self::CONFIG_KEY_SCHEMA];

    return $config;
  }

  public function toDictionary() {
    return array(
      'id' => $this->getID(),
      'phid' => $this->getPHID(),
      'transactionPHID' => $this->getTransactionPHID(),
      'x' => $this->getX(),
      'y' => $this->getY(),
      'width' => $this->getWidth(),
      'height' => $this->getHeight(),
    );
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

}
