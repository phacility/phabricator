<?php

final class PhabricatorRepositoryGitLFSRef
  extends PhabricatorRepositoryDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorDestructibleInterface {

  protected $repositoryPHID;
  protected $objectHash;
  protected $byteSize;
  protected $authorPHID;
  protected $filePHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectHash' => 'bytes64',
        'byteSize' => 'uint64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_hash' => array(
          'columns' => array('repositoryPHID', 'objectHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $file_phid = $this->getFilePHID();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($engine->getViewer())
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if ($file) {
      $engine->destroyObject($file);
    }

    $this->delete();
  }

}
