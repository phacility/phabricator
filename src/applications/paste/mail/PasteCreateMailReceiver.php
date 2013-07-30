<?php

/**
 * @group paste
 */
final class PasteCreateMailReceiver
  extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationPaste';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $config_key = 'metamta.paste.public-create-email';
    $create_address = PhabricatorEnv::getEnvConfig($config_key);
    if (!$create_address) {
      return false;
    }

    foreach ($mail->getToAddresses() as $to_address) {
      if ($this->matchAddresses($create_address, $to_address)) {
        return true;
      }
    }

    return false;
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $title = $mail->getSubject();
    if (!$title) {
      $title = pht('Pasted via email.');
    }
    $paste_file = PhabricatorFile::newFromFileData(
      $mail->getCleanTextBody(),
      array(
        'name' => $title,
        'mime-type' => 'text/plain; charset=utf-8',
        'authorPHID' => $sender->getPHID(),
      ));

    $paste = id(new PhabricatorPaste())
      ->setAuthorPHID($sender->getPHID())
      ->setTitle($title)
      ->setFilePHID($paste_file->getPHID())
      ->setLanguage('') // auto-detect
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
      ->save();

    $mail->setRelatedPHID($paste->getPHID());

    $subject = pht('You successfully created a paste.');
    $paste_uri = PhabricatorEnv::getProductionURI($paste->getURI());
    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($subject);
    $body->addTextSection(pht('PASTE LINK'), $paste_uri);

    id(new PhabricatorMetaMTAMail())
      ->addTos(array($sender->getPHID()))
      ->setSubject('[Paste] '.$subject)
      ->setFrom($sender->getPHID())
      ->setRelatedPHID($paste->getPHID())
      ->setBody($body->render())
      ->saveAndSend();

  }

}
