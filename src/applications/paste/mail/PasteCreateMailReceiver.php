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
    $xactions = array();
    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_CREATE)
      ->setNewValue(array(
        'title' => $title,
        'text' => $mail->getCleanTextBody()));
    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_TITLE)
      ->setNewValue($title);
    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_LANGUAGE)
      ->setNewValue(''); // auto-detect
    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
      ->setNewValue(PhabricatorPolicies::POLICY_USER);

    $paste = id(new PhabricatorPaste())
      ->setAuthorPHID($sender->getPHID());
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));
    $editor = id(new PhabricatorPasteEditor())
      ->setActor($sender)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true);
    $xactions = $editor->applyTransactions($paste, $xactions);

    $mail->setRelatedPHID($paste->getPHID());

    $subject_prefix =
      PhabricatorEnv::getEnvConfig('metamta.paste.subject-prefix');
    $subject = pht('You successfully created a paste.');
    $paste_uri = PhabricatorEnv::getProductionURI($paste->getURI());
    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($subject);
    $body->addTextSection(pht('PASTE LINK'), $paste_uri);

    id(new PhabricatorMetaMTAMail())
      ->addTos(array($sender->getPHID()))
      ->setSubject($subject)
      ->setSubjectPrefix($subject_prefix)
      ->setFrom($sender->getPHID())
      ->setRelatedPHID($paste->getPHID())
      ->setBody($body->render())
      ->saveAndSend();

  }

}
