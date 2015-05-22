<?php

final class PhabricatorSMSDemultiplexWorker extends PhabricatorSMSWorker {

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $task_data = $this->getTaskData();

    $to_numbers = idx($task_data, 'toNumbers');
    if (!$to_numbers) {
      // If we don't have any to numbers, don't send any sms.
      return;
    }

    foreach ($to_numbers as $number) {
      // NOTE: we will set the fromNumber and the proper provider data
      // in the `PhabricatorSMSSendWorker`.
      $sms = PhabricatorSMS::initializeNewSMS($task_data['body']);
      $sms->setToNumber($number);
      $sms->save();
      $this->queueTask(
        'PhabricatorSMSSendWorker',
        array(
          'smsID' => $sms->getID(),
        ));
    }
  }

}
