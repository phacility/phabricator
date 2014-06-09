<?php

final class PhabricatorSMSImplementationTwilioAdapter
  extends PhabricatorSMSImplementationAdapter {

  public function getProviderShortName() {
    return 'twilio';
  }

  /**
   * @phutil-external-symbol class Services_Twilio
   */
  private function buildClient() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/twilio-php/Services/Twilio.php';
    $account_sid = PhabricatorEnv::getEnvConfig('twilio.account-sid');
    $auth_token = PhabricatorEnv::getEnvConfig('twilio.auth-token');
    return new Services_Twilio($account_sid, $auth_token);
  }

  /**
   * @phutil-external-symbol class Services_Twilio_RestException
   */
  public function send() {
    $client = $this->buildClient();

    try {
      $message = $client->account->sms_messages->create(
        $this->formatNumberForSMS($this->getFrom()),
        $this->formatNumberForSMS($this->getTo()),
        $this->getBody(),
        array());
    } catch (Services_Twilio_RestException $e) {
      $message = sprintf(
        'HTTP Code %d: %s',
        $e->getStatus(),
        $e->getMessage());

      // Twilio tries to provide a link to more specific details if they can.
      if ($e->getInfo()) {
        $message .= sprintf(' For more information, see %s.', $e->getInfo());
      }
      throw new PhabricatorWorkerPermanentFailureException($message);
    }
    return $message;
  }

  public function getSMSDataFromResult($result) {
    return array($result->sid, $this->getSMSStatus($result->status));
  }

  public function pollSMSSentStatus(PhabricatorSMS $sms) {
    $client = $this->buildClient();
    $message = $client->account->messages->get($sms->getProviderSMSID());

    return $this->getSMSStatus($message->status);
  }

  /**
   * See https://www.twilio.com/docs/api/rest/sms#sms-status-values.
   */
  private function getSMSStatus($twilio_status) {
    switch ($twilio_status) {
      case 'failed':
        $status = PhabricatorSMS::STATUS_FAILED;
        break;
      case 'sent':
        $status = PhabricatorSMS::STATUS_SENT;
        break;
      case 'sending':
      case 'queued':
      default:
        $status = PhabricatorSMS::STATUS_SENT_UNCONFIRMED;
        break;
    }
    return $status;
  }

  /**
   * We expect numbers to be plainly entered - i.e. the preg_replace here
   * should do nothing - but try hard to format anyway.
   *
   * Twilio uses E164 format, e.g. +15551234567
   */
  private function formatNumberForSMS($number) {
    $number = preg_replace('/[^0-9]/', '', $number);
    $first_char = substr($number, 0, 1);
    switch ($first_char) {
      case '1':
        $prepend = '+';
        break;
      default:
        $prepend = '+1';
        break;
    }
    return $prepend.$number;
  }

}
