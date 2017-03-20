<?php

final class DifferentialReviewer
  extends DifferentialDAO {

  protected $revisionPHID;
  protected $reviewerPHID;
  protected $reviewerStatus;
  protected $lastActionDiffPHID;
  protected $lastCommentDiffPHID;

  private $authority = array();

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'reviewerStatus' => 'text64',
        'lastActionDiffPHID' => 'phid?',
        'lastCommentDiffPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_revision' => array(
          'columns' => array('revisionPHID', 'reviewerPHID'),
          'unique' => true,
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

}
