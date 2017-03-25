<?php

final class DifferentialReviewer
  extends DifferentialDAO {

  protected $revisionPHID;
  protected $reviewerPHID;
  protected $reviewerStatus;
  protected $lastActionDiffPHID;
  protected $lastCommentDiffPHID;
  protected $lastActorPHID;

  private $authority = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'reviewerStatus' => 'text64',
        'lastActionDiffPHID' => 'phid?',
        'lastCommentDiffPHID' => 'phid?',
        'lastActorPHID' => 'phid?',
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

  public function isAccepted($diff_phid) {
    $status_accepted = DifferentialReviewerStatus::STATUS_ACCEPTED;

    if ($this->getReviewerStatus() != $status_accepted) {
      return false;
    }

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

    $sticky_key = 'differential.sticky-accept';
    $is_sticky = PhabricatorEnv::getEnvConfig($sticky_key);

    if ($is_sticky) {
      return true;
    }

    return false;
  }

}
