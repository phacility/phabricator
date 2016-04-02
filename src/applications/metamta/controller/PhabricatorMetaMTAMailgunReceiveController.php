<?php

final class PhabricatorMetaMTAMailgunReceiveController
  extends PhabricatorMetaMTAController {

  public function shouldRequireLogin() {
    return false;
  }

  private function verifyMessage() {
    $api_key = PhabricatorEnv::getEnvConfig('mailgun.api-key');
    $request = $this->getRequest();
    $timestamp = $request->getStr('timestamp');
    $token = $request->getStr('token');
    $sig = $request->getStr('signature');
    $hash = hash_hmac('sha256', $timestamp.$token, $api_key);

    return phutil_hashes_are_identical($sig, $hash);
  }

  public function handleRequest(AphrontRequest $request) {

    // No CSRF for Mailgun.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    if (!$this->verifyMessage()) {
      throw new Exception(
        pht('Mail signature is not valid. Check your Mailgun API key.'));
    }

    $raw_headers = $request->getStr('message-headers');
    $raw_dict = array();
    if (strlen($raw_headers)) {
      $raw_headers = phutil_json_decode($raw_headers);
      foreach ($raw_headers as $raw_header) {
        list($name, $value) = $raw_header;
        $raw_dict[$name] = $value;
      }
    }

    $headers = array(
      'to'      => $request->getStr('recipient'),
      'from'    => $request->getStr('from'),
      'subject' => $request->getStr('subject'),
    ) + $raw_dict;

    $received = new PhabricatorMetaMTAReceivedMail();
    $received->setHeaders($headers);
    $received->setBodies(array(
      'text' => $request->getStr('stripped-text'),
      'html' => $request->getStr('stripped-html'),
    ));

    $file_phids = array();
    foreach ($_FILES as $file_raw) {
      try {
        $file = PhabricatorFile::newFromPHPUpload(
          $file_raw,
          array(
            'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
          ));
        $file_phids[] = $file->getPHID();
      } catch (Exception $ex) {
        phlog($ex);
      }
    }
    $received->setAttachments($file_phids);

    try {
      $received->save();
      $received->processReceivedMail();
    } catch (Exception $ex) {
      // We can get exceptions here in two cases.

      // First, saving the message may throw if we have already received a
      // message with the same Message ID. In this case, we're declining to
      // process a duplicate message, so failing silently is correct.

      // Second, processing the message may throw (for example, if it contains
      // an invalid !command). This will generate an email as a side effect,
      // so we don't need to explicitly handle the exception here.

      // In these cases, we want to return HTTP 200. If we do not, MailGun will
      // re-transmit the message later.
      phlog($ex);
    }

    $response = new AphrontWebpageResponse();
    $response->setContent(pht("Got it! Thanks, Mailgun!\n"));
    return $response;
  }

}
