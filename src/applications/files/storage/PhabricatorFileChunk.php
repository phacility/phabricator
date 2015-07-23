<?php

final class PhabricatorFileChunk extends PhabricatorFileDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $chunkHandle;
  protected $byteStart;
  protected $byteEnd;
  protected $dataFilePHID;

  private $dataFile = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'chunkHandle' => 'bytes12',
        'byteStart' => 'uint64',
        'byteEnd' => 'uint64',
        'dataFilePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_file' => array(
          'columns' => array('chunkHandle', 'byteStart', 'byteEnd'),
        ),
        'key_data' => array(
          'columns' => array('dataFilePHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function newChunkHandle() {
    $seed = Filesystem::readRandomBytes(64);
    return PhabricatorHash::digestForIndex($seed);
  }

  public static function initializeNewChunk($handle, $start, $end) {
    return id(new PhabricatorFileChunk())
      ->setChunkHandle($handle)
      ->setByteStart($start)
      ->setByteEnd($end);
  }

  public function attachDataFile(PhabricatorFile $file = null) {
    $this->dataFile = $file;
    return $this;
  }

  public function getDataFile() {
    return $this->assertAttached($this->dataFile);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }


  public function getPolicy($capability) {
    // These objects are low-level and only accessed through the storage
    // engine, so policies are mostly just in place to let us use the common
    // query infrastructure.
    return PhabricatorPolicies::getMostOpenPolicy();
  }


  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $data_phid = $this->getDataFilePHID();
    if ($data_phid) {
      $data_file = id(new PhabricatorFileQuery())
        ->setViewer($engine->getViewer())
        ->withPHIDs(array($data_phid))
        ->executeOne();
      if ($data_file) {
        $engine->destroyObject($data_file);
      }
    }

    $this->delete();
  }

}
