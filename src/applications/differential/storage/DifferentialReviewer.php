<?php

final class DifferentialReviewer {

  protected $reviewerPHID;
  protected $status;
  protected $diffID;

  public function __construct($reviewer_phid, $status, $diff_id = null) {
    $this->reviewerPHID = $reviewer_phid;
    $this->status = $status;
    $this->diffID = $diff_id;
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
    $user_type = PhabricatorPeoplePHIDTypeUser::TYPECONST;
    return (phid_get_type($this->getReviewerPHID()) == $user_type);
  }

}
