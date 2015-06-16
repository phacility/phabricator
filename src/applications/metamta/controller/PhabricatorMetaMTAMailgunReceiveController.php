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
    return hash_hmac('sha256', $timestamp.$token, $api_key) == $sig;

  }
  public function processRequest() {

    // No CSRF for Mailgun.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    if (!$this->verifyMessage()) {
      throw new Exception(
        pht('Mail signature is not valid. Check your Mailgun API key.'));
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    $raw_headers = $request->getStr('headers');
    $raw_headers = explode("\n", rtrim($raw_headers));
    $raw_dict = array();
    foreach (array_filter($raw_headers) as $header) {
      list($name, $value) = explode(':', $header, 2);
      $raw_dict[$name] = ltrim($value);
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
    $received->save();

    $received->processReceivedMail();

    $response = new AphrontWebpageResponse();
    $response->setContent(pht("Got it! Thanks, Mailgun!\n"));
    return $response;
  }

}
