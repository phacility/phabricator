<?php

final class DifferentialReviewer
  extends DifferentialDAO {

  protected $revisionPHID;
  protected $reviewerPHID;
  protected $reviewerStatus;
  protected $lastActionDiffPHID;
  protected $lastCommentDiffPHID;
  protected $lastActorPHID;
  protected $voidedPHID;

  private $authority = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'reviewerStatus' => 'text64',
        'lastActionDiffPHID' => 'phid?',
        'lastCommentDiffPHID' => 'phid?',
        'lastActorPHID' => 'phid?',
        'voidedPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_revision' => array(
          'columns' => array('revisionPHID', 'reviewerPHID'),
          'unique' => true,
        ),
        'key_reviewer' => array(
          'columns' => array('reviewerPHID', 'revisionPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function isUser() {
    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    return (phid_get_type($this->getReviewerPHID()) == $user_type);
  }

  public function isPackage() {
    $package_type = PhabricatorOwnersPackagePHIDType::TYPECONST;
    return (phid_get_type($this->getReviewerPHID()) == $package_type);
  }

  public function attachAuthority(PhabricatorUser $user, $has_authority) {
    $this->authority[$user->getCacheFragment()] = $has_authority;
    return $this;
  }

  public function hasAuthority(PhabricatorUser $viewer) {
    $cache_fragment = $viewer->getCacheFragment();
    return $this->assertAttachedKey($this->authority, $cache_fragment);
  }

  public function isResigned() {
    $status_resigned = DifferentialReviewerStatus::STATUS_RESIGNED;
    return ($this->getReviewerStatus() == $status_resigned);
  }

  public function isRejected($diff_phid) {
    $status_rejected = DifferentialReviewerStatus::STATUS_REJECTED;

    if ($this->getReviewerStatus() != $status_rejected) {
      return false;
    }

    if ($this->getVoidedPHID()) {
      return false;
    }

    if ($this->isCurrentAction($diff_phid)) {
      return true;
    }

    return false;
  }


  public function isAccepted($diff_phid) {
    $status_accepted = DifferentialReviewerStatus::STATUS_ACCEPTED;

    if ($this->getReviewerStatus() != $status_accepted) {
      return false;
    }

    // If this accept has been voided (for example, but a reviewer using
    // "Request Review"), don't count it as a real "Accept" even if it is
    // against the current diff PHID.
    if ($this->getVoidedPHID()) {
      return false;
    }

    if ($this->isCurrentAction($diff_phid)) {
      return true;
    }

    $sticky_key = 'differential.sticky-accept';
    $is_sticky = PhabricatorEnv::getEnvConfig($sticky_key);

    if ($is_sticky) {
      return true;
    }

    return false;
  }

  private function isCurrentAction($diff_phid) {
    if (!$diff_phid) {
      return true;
    }

    $action_phid = $this->getLastActionDiffPHID();

    if (!$action_phid) {
      return true;
    }

    if ($action_phid == $diff_phid) {
      return true;
    }

    return false;
  }

}
