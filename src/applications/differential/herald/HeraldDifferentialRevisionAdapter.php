<?php

final class HeraldDifferentialRevisionAdapter
  extends HeraldDifferentialAdapter
  implements HarbormasterBuildableAdapterInterface {

  protected $revision;

  protected $affectedPackages;
  protected $changesets;
  private $haveHunks;

  private $buildRequests = array();

  public function getAdapterApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function newObject() {
    return new DifferentialRevision();
  }

  public function isTestAdapterForObject($object) {
    return ($object instanceof DifferentialRevision);
  }

  public function getAdapterTestDescription() {
    return pht(
      'Test rules which run when a revision is created or updated.');
  }

  public function newTestAdapter(PhabricatorUser $viewer, $object) {
    return self::newLegacyAdapter(
      $object,
      $object->loadActiveDiff());
  }

  protected function initializeNewAdapter() {
    $this->revision = $this->newObject();
  }

  public function getObject() {
    return $this->revision;
  }

  public function getAdapterContentType() {
    return 'differential';
  }

  public function getAdapterContentName() {
    return pht('Differential Revisions');
  }

  public function getAdapterContentDescription() {
    return pht(
      "React to revisions being created or updated.\n".
      "Revision rules can send email, flag revisions, add reviewers, ".
      "and run build plans.");
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function getRepetitionOptions() {
    return array(
      HeraldRepetitionPolicyConfig::EVERY,
      HeraldRepetitionPolicyConfig::FIRST,
    );
  }

  public static function newLegacyAdapter(
    DifferentialRevision $revision,
    DifferentialDiff $diff) {
    $object = new HeraldDifferentialRevisionAdapter();

    // Reload the revision to pick up relationship information.
    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($revision->getID()))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->needReviewers(true)
      ->executeOne();

    $object->revision = $revision;
    $object->setDiff($diff);

    return $object;
  }

  public function getHeraldName() {
    return $this->revision->getTitle();
  }

  protected function loadChangesets() {
    if ($this->changesets === null) {
      $this->changesets = $this->getDiff()->loadChangesets();
    }
    return $this->changesets;
  }

  protected function loadChangesetsWithHunks() {
    $changesets = $this->loadChangesets();

    if ($changesets && !$this->haveHunks) {
      $this->haveHunks = true;

      id(new DifferentialHunkQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withChangesets($changesets)
        ->needAttachToChangesets(true)
        ->execute();
    }

    return $changesets;
  }

  public function loadAffectedPackages() {
    if ($this->affectedPackages === null) {
      $this->affectedPackages = array();

      $repository = $this->loadRepository();
      if ($repository) {
        $packages = PhabricatorOwnersPackage::loadAffectedPackages(
          $repository,
          $this->loadAffectedPaths());
        $this->affectedPackages = $packages;
      }
    }
    return $this->affectedPackages;
  }

  public function loadReviewers() {
    return $this->getObject()->getReviewerPHIDs();
  }


/* -(  HarbormasterBuildableAdapterInterface  )------------------------------ */


  public function getHarbormasterBuildablePHID() {
    return $this->getDiff()->getPHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getObject()->getPHID();
  }

  public function getQueuedHarbormasterBuildRequests() {
    return $this->buildRequests;
  }

  public function queueHarbormasterBuildRequest(
    HarbormasterBuildRequest $request) {
    $this->buildRequests[] = $request;
  }

}
