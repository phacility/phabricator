<?php

/**
 * Records a push to a hosted repository. This allows us to store metadata
 * about who pushed commits, when, and from where. We can also record the
 * history of branches and tags, which is not normally persisted outside of
 * the reflog.
 *
 * This log is written by commit hooks installed into hosted repositories.
 * See @{class:DiffusionCommitHookEngine}.
 */
final class PhabricatorRepositoryPushLog
  extends PhabricatorRepositoryDAO
  implements PhabricatorPolicyInterface {

  const REFTYPE_BRANCH = 'branch';
  const REFTYPE_TAG = 'tag';
  const REFTYPE_BOOKMARK = 'bookmark';
  const REFTYPE_COMMIT = 'commit';

  const CHANGEFLAG_ADD = 1;
  const CHANGEFLAG_DELETE = 2;
  const CHANGEFLAG_APPEND = 4;
  const CHANGEFLAG_REWRITE = 8;
  const CHANGEFLAG_DANGEROUS = 16;

  const REJECT_ACCEPT = 0;
  const REJECT_DANGEROUS = 1;
  const REJECT_HERALD = 2;
  const REJECT_EXTERNAL = 3;
  const REJECT_BROKEN = 4;

  protected $repositoryPHID;
  protected $epoch;
  protected $pusherPHID;
  protected $remoteAddress;
  protected $remoteProtocol;
  protected $transactionKey;
  protected $refType;
  protected $refNameHash;
  protected $refNameRaw;
  protected $refNameEncoding;
  protected $refOld;
  protected $refNew;
  protected $mergeBase;
  protected $changeFlags;
  protected $rejectCode;
  protected $rejectDetails;

  private $dangerousChangeDescription = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  public static function initializeNewLog(PhabricatorUser $viewer) {
    return id(new PhabricatorRepositoryPushLog())
      ->setPusherPHID($viewer->getPHID());
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPHIDTypePushLog::TYPECONST);
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function getRefName() {
    if ($this->getRefNameEncoding() == 'utf8') {
      return $this->getRefNameRaw();
    }
    return phutil_utf8ize($this->getRefNameRaw());
  }

  public function setRefName($ref_raw) {
    $encoding = phutil_is_utf8($ref_raw) ? 'utf8' : null;

    $this->setRefNameRaw($ref_raw);
    $this->setRefNameHash(PhabricatorHash::digestForIndex($ref_raw));
    $this->setRefNameEncoding($encoding);

    return $this;
  }

  public function getRefOldShort() {
    if ($this->getRepository()->isSVN()) {
      return $this->getRefOld();
    }
    return substr($this->getRefOld(), 0, 12);
  }

  public function getRefNewShort() {
    if ($this->getRepository()->isSVN()) {
      return $this->getRefNew();
    }
    return substr($this->getRefNew(), 0, 12);
  }

  public function hasChangeFlags($mask) {
    return ($this->changeFlags & $mask);
  }

  public function attachDangerousChangeDescription($description) {
    $this->dangerousChangeDescription = $description;
    return $this;
  }

  public function getDangerousChangeDescription() {
    return $this->assertAttached($this->dangerousChangeDescription);
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
    return pht(
      "A repository's push logs are visible to users who can see the ".
      "repository.");
  }

}
