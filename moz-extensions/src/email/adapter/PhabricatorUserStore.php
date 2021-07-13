<?php


class PhabricatorUserStore {
  /** @var array<string, PhabricatorUser> */
  private array $PHIDCache;
  /** @var array<string, PhabricatorUser> */
  private array $usernameCache;

  public function __construct() {
    $this->PHIDCache = [];
    $this->usernameCache = [];
  }

  public function find(string $PHID): PhabricatorUser {
    if (array_key_exists($PHID, $this->PHIDCache)) {
      return $this->PHIDCache[$PHID];
    }

    $user = (new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs([$PHID])
      ->executeOne();
    $this->PHIDCache[$PHID] = $user;
    return $user;
  }

  public function findByName(string $name): ?PhabricatorUser {
    if (array_key_exists($name, $this->usernameCache)) {
      return $this->usernameCache[$name];
    }

    $user = (new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUsernames([$name])
      ->withIsDisabled(false)
      ->executeOne();
    $this->usernameCache[$name] = $user;
    return $user;
  }

  /**
   * @return PhabricatorUser[]
   */
  public function findAllBySubscribersById(string $PHID): array {
    if (substr($PHID, 0, strlen('PHID-PROJ')) === "PHID-PROJ") {
      // This is a group subscriber
      $project = self::fetchProject($PHID);
      return array_values($this->queryAll($project->getMemberPHIDs()));
    } else if (substr($PHID, 0, strlen('PHID-OPKG')) == 'PHID-OPKG') {
      // This is a "code owner" subscriber
      $package = self::fetchPackage($PHID);
      return array_values($this->queryAll($package->getOwnerPHIDs()));
    } else {
      // PHID type must be "PHID-USER".
      return [$this->find($PHID)];
    }
  }

  public function findReviewerByPHID(string $PHID): PhabricatorReviewer {
    if (substr($PHID, 0, strlen('PHID-PROJ')) === "PHID-PROJ") {
      // This is a group reviewer
      $project = self::fetchProject($PHID);
      $users = array_values($this->queryAll($project->getMemberPHIDs()));
      return new GroupPhabricatorReviewer($project->getDisplayName(), $users);
    } else if (substr($PHID, 0, strlen('PHID-OPKG')) == 'PHID-OPKG') {
      // This is a "code owner" reviewer
      $package = self::fetchPackage($PHID);
      $users = array_values($this->queryAll($package->getOwnerPHIDs()));
      return new GroupPhabricatorReviewer($package->getName(), $users);
    } else {
      // PHID type must be "PHID-USER".
      // So, this is a single user reviewer.
      return new UserPhabricatorReviewer($this->find($PHID));
    }
  }

  /**
   * @param string[] $PHIDs
   * @return PhabricatorUser[]
   */
  public function queryAll(array $PHIDs): array {
    $users = (new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($PHIDs)
      ->execute();

    foreach ($users as $user) {
      $this->PHIDCache[$user->getPHID()] = $user;
    }
    return $users;
  }

  private static function fetchProject(string $PHID): PhabricatorProject {
    return (new PhabricatorProjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->needMembers(true)
      ->withPHIDs([$PHID])
      ->executeOne();
  }

  private static function fetchPackage(string $PHID): PhabricatorOwnersPackage {
    return (new PhabricatorOwnersPackageQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs([$PHID])
      ->executeOne();
  }
}