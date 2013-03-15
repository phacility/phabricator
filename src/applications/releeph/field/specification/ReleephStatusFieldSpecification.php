<?php

final class ReleephStatusFieldSpecification
  extends ReleephFieldSpecification {

  public function getName() {
    return 'Status';
  }

  public function renderValueForHeaderView() {
    return id(new ReleephRequestStatusView())
      ->setReleephRequest($this->getReleephRequest())
      ->render();
  }

  private static $filters = array(
    'req' => ReleephRequest::STATUS_REQUESTED,
    'app' => ReleephRequest::STATUS_NEEDS_PICK,
    'rej' => ReleephRequest::STATUS_REJECTED,
    'abn' => ReleephRequest::STATUS_ABANDONED,
    'mer' => ReleephRequest::STATUS_PICKED,
    'rrq' => ReleephRequest::STATUS_NEEDS_REVERT,
    'rev' => ReleephRequest::STATUS_REVERTED,
  );

  protected function appendSelectControls(
    AphrontFormView $form,
    AphrontRequest $request,
    array $all_releeph_requests,
    array $all_releeph_requests_without_this_field) {

    $filter_names = array(
      null => 'All',
    );

    foreach (self::$filters as $code => $status) {
      $name = ReleephRequest::getStatusDescriptionFor($status);
      $filter_names[$code] = $name;
    }

    $key = 'status';
    $code = $request->getStr($key);
    $current_status = idx(self::$filters, $code);

    $codes = array_flip(self::$filters);

    $counters = array(null => count($all_releeph_requests_without_this_field));
    foreach ($all_releeph_requests_without_this_field as $releeph_request) {
      $this_status = $releeph_request->getStatus();
      $this_code = idx($codes, $this_status);
      if (!isset($counters[$this_code])) {
        $counters[$this_code] = 0;
      }
      $counters[$this_code]++;
    }

    $control = id(new AphrontFormCountedToggleButtonsControl())
      ->setLabel($this->getName())
      ->setValue($code)
      ->setBaseURI($request->getRequestURI(), $key)
      ->setButtons($filter_names)
      ->setCounters($counters);

    $form
      ->appendChild($control)
      ->addHiddenInput($key, $code);
  }

  protected function selectReleephRequests(AphrontRequest $request,
                                           array &$releeph_requests) {

    $key = 'status';
    $code = $request->getStr($key);
    if (!$code) {
      return;
    }

    $current_status = idx(self::$filters, $code);

    $filtered = array();
    foreach ($releeph_requests as $releeph_request) {
      if ($releeph_request->getStatus() == $current_status) {
        $filtered[] = $releeph_request;
      }
    }
    $releeph_requests = $filtered;
  }

}
