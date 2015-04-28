<?php

final class UserRemoveStatusConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.removestatus';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodDescription() {
    return pht('Delete status information of the logged-in user.');
  }

  public function getMethodStatusDescription() {
    return pht(
      'Statuses are becoming full-fledged events as part of the '.
      'Calendar application.');
  }

  protected function defineParamTypes() {
    return array(
      'fromEpoch' => 'required int',
      'toEpoch' => 'required int',
    );
  }

  protected function defineReturnType() {
    return 'int';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-BAD-EPOCH' => "'toEpoch' must be bigger than 'fromEpoch'.",
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user_phid = $request->getUser()->getPHID();
    $from = $request->getValue('fromEpoch');
    $to = $request->getValue('toEpoch');

    if ($to <= $from) {
      throw new ConduitException('ERR-BAD-EPOCH');
    }

    $table = new PhabricatorCalendarEvent();
    $table->openTransaction();
    $table->beginReadLocking();

    $overlap = $table->loadAllWhere(
      'userPHID = %s AND dateFrom < %d AND dateTo > %d',
      $user_phid,
      $to,
      $from);
    foreach ($overlap as $status) {
      if ($status->getDateFrom() < $from) {
        if ($status->getDateTo() > $to) {
          // Split the interval.
          id(new PhabricatorCalendarEvent())
            ->setUserPHID($user_phid)
            ->setDateFrom($to)
            ->setDateTo($status->getDateTo())
            ->setStatus($status->getStatus())
            ->setDescription($status->getDescription())
            ->save();
        }
        $status->setDateTo($from);
        $status->save();
      } else if ($status->getDateTo() > $to) {
        $status->setDateFrom($to);
        $status->save();
      } else {
        $status->delete();
      }
    }

    $table->endReadLocking();
    $table->saveTransaction();
    return count($overlap);
  }

}
