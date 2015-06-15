<?php

abstract class PhabricatorSMSImplementationAdapter extends Phobject {

  private $fromNumber;
  private $toNumber;
  private $body;

  public function setFrom($number) {
    $this->fromNumber = $number;
    return $this;
  }

  public function getFrom() {
    return $this->fromNumber;
  }

  public function setTo($number) {
    $this->toNumber = $number;
    return $this;
  }

  public function getTo() {
    return $this->toNumber;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function getBody() {
    return $this->body;
  }

  /**
   * 16 characters or less, to be used in database columns and exposed
   * to administrators during configuration directly.
   */
  abstract public function getProviderShortName();

  /**
   * Send the message. Generally, this means connecting to some service and
   * handing data to it. SMS APIs are generally asynchronous, so truly
   * determining success or failure is probably impossible synchronously.
   *
   * That said, if the adapter determines that the SMS will never be
   * deliverable, or there is some other known failure, it should throw
   * an exception.
   *
   * @return null
   */
  abstract public function send();

  /**
   * Most (all?) SMS APIs are asynchronous, but some do send back some
   * initial information. Use this hook to determine what the updated
   * sentStatus should be and what the provider is using for an SMS ID,
   * as well as throw exceptions if there are any failures.
   *
   * @return array Tuple of ($sms_id and $sent_status)
   */
  abstract public function getSMSDataFromResult($result);

  /**
   * Due to the asynchronous nature of sending SMS messages, it can be
   * necessary to poll the provider regarding the sent status of a given
   * sms.
   *
   * For now, this *MUST* be implemented and *MUST* work.
   */
  abstract public function pollSMSSentStatus(PhabricatorSMS $sms);

  /**
   * Convenience function to handle sending an SMS.
   */
  public static function sendSMS(array $to_numbers, $body) {
    PhabricatorWorker::scheduleTask(
      'PhabricatorSMSDemultiplexWorker',
      array(
        'toNumbers'  => $to_numbers,
        'body'       => $body,
      ),
      array(
        'priority' => PhabricatorWorker::PRIORITY_ALERTS,
      ));
  }
}
