<?php

/**
 * @group conduit
 */
final class ConduitAPI_user_removestatus_Method extends ConduitAPI_user_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return "Delete status information of the logged-in user.";
  }

  public function defineParamTypes() {
    return array(
      'fromEpoch' => 'required int',
      'toEpoch' => 'required int',
    );
  }

  public function defineReturnType() {
    return 'int';
  }

  public function defineErrorTypes() {
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

    $table = new PhabricatorUserStatus();
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
          id(new PhabricatorUserStatus())
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
