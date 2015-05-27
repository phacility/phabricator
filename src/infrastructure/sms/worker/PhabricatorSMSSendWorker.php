<?php

final class PhabricatorSMSSendWorker extends PhabricatorSMSWorker {

  public function getMaximumRetryCount() {
    return PhabricatorSMS::MAXIMUM_SEND_TRIES;
  }

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    return phutil_units('1 minute in seconds');
  }

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $task_data = $this->getTaskData();

    $sms = id(new PhabricatorSMS())
      ->loadOneWhere('id = %d', $task_data['smsID']);

    if (!$sms) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('SMS object was not found.'));
    }

    // this has the potential to be updated asynchronously
    if ($sms->getSendStatus() == PhabricatorSMS::STATUS_SENT) {
      return;
    }

    $adapter = PhabricatorEnv::getEnvConfig('sms.default-adapter');
    $adapter = newv($adapter, array());
    if ($sms->hasBeenSentAtLeastOnce()) {
      $up_to_date_status = $adapter->pollSMSSentStatus($sms);
      if ($up_to_date_status) {
        $sms->setSendStatus($up_to_date_status);
        if ($up_to_date_status  == PhabricatorSMS::STATUS_SENT) {
          $sms->save();
          return;
        }
      }
      // TODO - re-jigger this so we can try if appropos (e.g. rate limiting)
      return;
    }

    $from_number = PhabricatorEnv::getEnvConfig('sms.default-sender');
    // always set the from number if we get this far in case of configuration
    // changes.
    $sms->setFromNumber($from_number);

    $adapter->setTo($sms->getToNumber());
    $adapter->setFrom($sms->getFromNumber());
    $adapter->setBody($sms->getBody());
    // give the provider name the same treatment as phone number
    $sms->setProviderShortName($adapter->getProviderShortName());

    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $sms->setSendStatus(PhabricatorSMS::STATUS_FAILED_PERMANENTLY);
      $sms->save();
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Phabricator is running in silent mode. See `%s` '.
          'in the configuration to change this setting.',
          'phabricator.silent'));
    }

    try {
      $result = $adapter->send();
      list($sms_id, $sent_status) = $adapter->getSMSDataFromResult($result);
    } catch (PhabricatorWorkerPermanentFailureException $e) {
      $sms->setSendStatus(PhabricatorSMS::STATUS_FAILED_PERMANENTLY);
      $sms->save();
      throw $e;
    } catch (Exception $e) {
      $sms->setSendStatus(PhabricatorSMS::STATUS_FAILED_PERMANENTLY);
      $sms->save();
      throw new PhabricatorWorkerPermanentFailureException(
        $e->getMessage());
    }
    $sms->setProviderSMSID($sms_id);
    $sms->setSendStatus($sent_status);
    $sms->save();
  }

}
