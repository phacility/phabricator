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
  const CHANGEFLAG_ENORMOUS = 32;

  const REJECT_ACCEPT = 0;
  const REJECT_DANGEROUS = 1;
  const REJECT_HERALD = 2;
  const REJECT_EXTERNAL = 3;
  const REJECT_BROKEN = 4;
  const REJECT_ENORMOUS = 5;

  protected $repositoryPHID;
  protected $epoch;
  protected $pusherPHID;
  protected $pushEventPHID;
  protected $devicePHID;
  protected $refType;
  protected $refNameHash;
  protected $refNameRaw;
  protected $refNameEncoding;
  protected $refOld;
  protected $refNew;
  protected $mergeBase;
  protected $changeFlags;

  private $dangerousChangeDescription = self::ATTACHABLE;
  private $pushEvent = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  public static function initializeNewLog(PhabricatorUser $viewer) {
    return id(new PhabricatorRepositoryPushLog())
      ->setPusherPHID($viewer->getPHID());
  }

  public static function getHeraldChangeFlagConditionOptions() {
    return array(
      self::CHANGEFLAG_ADD =>
        pht('change creates ref'),
      self::CHANGEFLAG_DELETE =>
        pht('change deletes ref'),
      self::CHANGEFLAG_REWRITE =>
        pht('change rewrites ref'),
      self::CHANGEFLAG_DANGEROUS =>
        pht('dangerous change'),
    );
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_BINARY => array(
        'refNameRaw' => true,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'refType' => 'text12',
        'refNameHash' => 'bytes12?',
        'refNameRaw' => 'bytes?',
        'refNameEncoding' => 'text16?',
        'refOld' => 'text40?',
        'refNew' => 'text40',
        'mergeBase' => 'text40?',
        'changeFlags' => 'uint32',
        'devicePHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_repository' => array(
          'columns' => array('repositoryPHID'),
        ),
        'key_ref' => array(
          'columns' => array('repositoryPHID', 'refNew'),
        ),
        'key_name' => array(
          'columns' => array('repositoryPHID', 'refNameHash'),
        ),
        'key_event' => array(
          'columns' => array('pushEventPHID'),
        ),
        'key_pusher' => array(
          'columns' => array('pusherPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRepositoryPushLogPHIDType::TYPECONST);
  }

  public function attachPushEvent(PhabricatorRepositoryPushEvent $push_event) {
    $this->pushEvent = $push_event;
    return $this;
  }

  public function getPushEvent() {
    return $this->assertAttached($this->pushEvent);
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

  public function attachRepository(PhabricatorRepository $repository) {
    // NOTE: Some gymnastics around this because of object construction order
    // in the hook engine. Particularly, web build the logs before we build
    // their push event.
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    if ($this->repository == self::ATTACHABLE) {
      return $this->getPushEvent()->getRepository();
    }
    return $this->assertAttached($this->repository);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    // NOTE: We're passing through the repository rather than the push event
    // mostly because we need to do policy checks in Herald before we create
    // the event. The two approaches are equivalent in practice.
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
