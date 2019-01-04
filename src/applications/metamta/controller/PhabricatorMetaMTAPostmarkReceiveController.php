<?php

final class PhabricatorMetaMTAPostmarkReceiveController
  extends PhabricatorMetaMTAController {

  public function shouldRequireLogin() {
    return false;
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function handleRequest(AphrontRequest $request) {
    // Don't process requests if we don't have a configured Postmark adapter.
    $mailers = PhabricatorMetaMTAMail::newMailers(
      array(
        'inbound' => true,
        'types' => array(
          PhabricatorMailPostmarkAdapter::ADAPTERTYPE,
        ),
      ));
    if (!$mailers) {
      return new Aphront404Response();
    }

    $remote_address = $request->getRemoteAddress();
    $any_remote_match = false;
    foreach ($mailers as $mailer) {
      $inbound_addresses = $mailer->getOption('inbound-addresses');
      $cidr_list = PhutilCIDRList::newList($inbound_addresses);
      if ($cidr_list->containsAddress($remote_address)) {
        $any_remote_match = true;
        break;
      }
    }

    if (!$any_remote_match) {
      return new Aphront400Response();
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $raw_input = PhabricatorStartup::getRawInput();

    try {
      $data = phutil_json_decode($raw_input);
    } catch (Exception $ex) {
      return new Aphront400Response();
    }

    $raw_headers = array();
    $header_items = idx($data, 'Headers', array());
    foreach ($header_items as $header_item) {
      $name = idx($header_item, 'Name');
      $value = idx($header_item, 'Value');
      $raw_headers[$name] = $value;
    }

    $headers = array(
      'to' => idx($data, 'To'),
      'from' => idx($data, 'From'),
      'cc' => idx($data, 'Cc'),
      'subject' => idx($data, 'Subject'),
    ) + $raw_headers;


    $received = id(new PhabricatorMetaMTAReceivedMail())
      ->setHeaders($headers)
      ->setBodies(
        array(
          'text' => idx($data, 'TextBody'),
          'html' => idx($data, 'HtmlBody'),
        ));

    $file_phids = array();
    $attachments = idx($data, 'Attachments', array());
    foreach ($attachments as $attachment) {
      $file_data = idx($attachment, 'Content');
      $file_data = base64_decode($file_data);

      try {
        $file = PhabricatorFile::newFromFileData(
          $file_data,
          array(
            'name' => idx($attachment, 'Name'),
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
      phlog($ex);
    }

    return id(new AphrontWebpageResponse())
      ->setContent(pht("Got it! Thanks, Postmark!\n"));
  }

}
