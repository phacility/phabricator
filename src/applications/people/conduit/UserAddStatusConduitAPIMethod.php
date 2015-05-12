<?php

final class UserAddStatusConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.addstatus';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodDescription() {
    return pht('Add status information to the logged-in user.');
  }

  public function getMethodStatusDescription() {
    return pht(
      'Statuses are becoming full-fledged events as part of the '.
      'Calendar application.');
  }

  protected function defineParamTypes() {
    $status_const = $this->formatStringConstants(array('away', 'sporadic'));

    return array(
      'fromEpoch'   => 'required int',
      'toEpoch'     => 'required int',
      'status'      => 'required '.$status_const,
      'description' => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-EPOCH' => "'toEpoch' must be bigger than 'fromEpoch'.",
      'ERR-OVERLAP'   =>
        'There must be no status in any part of the specified epoch.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user_phid   = $request->getUser()->getPHID();
    $from        = $request->getValue('fromEpoch');
    $to          = $request->getValue('toEpoch');
    $status      = $request->getValue('status');
    $description = $request->getValue('description', '');

    id(new PhabricatorCalendarEvent())
      ->setUserPHID($user_phid)
      ->setDateFrom($from)
      ->setDateTo($to)
      ->setTextStatus($status)
      ->setDescription($description)
      ->save();
  }

}
