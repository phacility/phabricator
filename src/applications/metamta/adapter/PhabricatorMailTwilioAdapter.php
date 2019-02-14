<?php

final class PhabricatorMailTwilioAdapter
  extends PhabricatorMailAdapter {

  const ADAPTERTYPE = 'twilio';

  public function getSupportedMessageTypes() {
    return array(
      PhabricatorMailSMSMessage::MESSAGETYPE,
    );
  }

  protected function validateOptions(array $options) {
    PhutilTypeSpec::checkMap(
      $options,
      array(
        'account-sid' => 'string',
        'auth-token' => 'string',
        'from-number' => 'string',
      ));

    // Construct an object from the "from-number" to validate it.
    $number = new PhabricatorPhoneNumber($options['from-number']);
  }

  public function newDefaultOptions() {
    return array(
      'account-sid' => null,
      'auth-token' => null,
      'from-number' => null,
    );
  }

  public function sendMessage(PhabricatorMailExternalMessage $message) {
    $account_sid = $this->getOption('account-sid');

    $auth_token = $this->getOption('auth-token');
    $auth_token = new PhutilOpaqueEnvelope($auth_token);

    $from_number = $this->getOption('from-number');
    $from_number = new PhabricatorPhoneNumber($from_number);

    $to_number = $message->getToNumber();
    $text_body = $message->getTextBody();

    $parameters = array(
      'From' => $from_number->toE164(),
      'To' => $to_number->toE164(),
      'Body' => $text_body,
    );

    $result = id(new PhabricatorTwilioFuture())
      ->setAccountSID($account_sid)
      ->setAuthToken($auth_token)
      ->setMethod('Messages.json', $parameters)
      ->setTimeout(60)
      ->resolve();
  }

}
