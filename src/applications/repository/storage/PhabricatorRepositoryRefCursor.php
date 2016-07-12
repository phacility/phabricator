<?php

/**
 * Stores the previous value of a ref (like a branch or tag) so we can figure
 * out how a repository has changed when we discover new commits or branch
 * heads.
 */
final class PhabricatorRepositoryRefCursor
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  const TYPE_BRANCH = 'branch';
  const TYPE_TAG = 'tag';
  const TYPE_BOOKMARK = 'bookmark';
  const TYPE_REF = 'ref';

  protected $repositoryPHID;
  protected $refType;
  protected $refNameHash;
  protected $refNameRaw;
  protected $refNameEncoding;
  protected $commitIdentifier;
  protected $isClosed = 0;

  private $repository = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_BINARY => array(
        'refNameRaw' => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'refType' => 'text32',
        'refNameHash' => 'bytes12',
        'commitIdentifier' => 'text40',
        'refNameEncoding' => 'text16?',
        'isClosed' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_cursor' => array(
          'columns' => array('repositoryPHID', 'refType', 'refNameHash'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryRefCursorPHIDType::TYPECONST);
  }

  public function getRefName() {
    return $this->getUTF8StringFromStorage(
      $this->getRefNameRaw(),
      $this->getRefNameEncoding());
  }

  public function setRefName($ref_raw) {
    $this->setRefNameRaw($ref_raw);
    $this->setRefNameHash(PhabricatorHash::digestForIndex($ref_raw));
    $this->setRefNameEncoding($this->detectEncodingForStorage($ref_raw));

    return $this;
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getRepository()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getRepository()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Repository refs have the same policies as their repository.');
  }

}
