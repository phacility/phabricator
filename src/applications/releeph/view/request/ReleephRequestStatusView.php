<?php

final class ReleephRequestStatusView extends AphrontView {

  private $releephRequest;

  public function setReleephRequest(ReleephRequest $rq) {
    $this->releephRequest = $rq;
    return $this;
  }

  public function render() {
    require_celerity_resource('releeph-status');

    $request = $this->releephRequest;
    $status = $request->getStatus();
    $pick_status = $request->getPickStatus();

    $description = ReleephRequest::getStatusDescriptionFor($status);

    $warning = null;

    if ($status == ReleephRequest::STATUS_NEEDS_PICK) {
      if ($pick_status == ReleephRequest::PICK_FAILED) {
        $warning = 'Last pick failed!';
      }
    } elseif ($status == ReleephRequest::STATUS_NEEDS_REVERT) {
      if ($pick_status == ReleephRequest::REVERT_FAILED) {
        $warning = 'Last revert failed!';
      }
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'releeph-status',
      ),
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'description',
          ),
          $description),
        phutil_tag(
          'div',
          array(
            'class' => 'warning',
          ),
          $warning)));
  }

}
