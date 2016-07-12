<?php

final class DifferentialReviewer extends Phobject {

  private $reviewerPHID;
  private $status;
  private $diffID;
  private $authority = array();

  public function __construct($reviewer_phid, array $edge_data) {
    $this->reviewerPHID = $reviewer_phid;
    $this->status = idx($edge_data, 'status');
    $this->diffID = idx($edge_data, 'diff');
  }

  public function getReviewerPHID() {
    return $this->reviewerPHID;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getDiffID() {
    return $this->diffID;
  }

  public function isUser() {
    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;
    return (phid_get_type($this->getReviewerPHID()) == $user_type);
  }

  public function attachAuthority(PhabricatorUser $user, $has_authority) {
    $this->authority[$user->getPHID()] = $has_authority;
    return $this;
  }

  public function hasAuthority(PhabricatorUser $viewer) {
    // It would be nice to use assertAttachedKey() here, but we don't extend
    // PhabricatorLiskDAO, and faking that seems sketchy.

    $viewer_phid = $viewer->getPHID();
    if (!array_key_exists($viewer_phid, $this->authority)) {
      throw new Exception(pht('You must %s first!', 'attachAuthority()'));
    }
    return $this->authority[$viewer_phid];
  }

  public function getEdgeData() {
    return array(
      'status' => $this->status,
      'diffID' => $this->diffID,
    );
  }

}
