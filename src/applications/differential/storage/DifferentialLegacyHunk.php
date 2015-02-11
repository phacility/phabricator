<?php

final class DifferentialLegacyHunk extends DifferentialHunk {

  protected $changes;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'changes' => 'text?',
        'oldOffset' => 'uint32',
        'oldLen' => 'uint32',
        'newOffset' => 'uint32',
        'newLen' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'changesetID' => array(
          'columns' => array('changesetID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getTableName() {
    return 'differential_hunk';
  }

  public function getDataEncoding() {
    return 'utf8';
  }

  public function forceEncoding($encoding) {
    // Not supported, these are always utf8.
    return $this;
  }

}
