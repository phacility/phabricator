<?php

final class DifferentialReviewer {

  protected $reviewerPHID;
  protected $status;
  protected $diffID;

  public function __construct($reviewer_phid, $status, $diff_id = null) {
    $this->reviewerPHID = $reviewer_phid;
    $this->setStatus($status, $diff_id);
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

  public function setStatus($status, $diff_id = null) {
    if ($status == DifferentialReviewerStatus::STATUS_REJECTED
      && $diff_id === null) {

      throw new Exception('STATUS_REJECTED must have a diff_id set');
    }

    $this->status = $status;
    $this->diffID = $diff_id;

    return $this;
  }

}
