<?php

final class PasteCreateMailReceiver extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorPasteApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $paste_app = new PhabricatorPasteApplication();
    return $this->canAcceptApplicationMail($paste_app, $mail);
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $title = $mail->getSubject();
    if (!$title) {
      $title = pht('Email Paste');
    }

    $xactions = array();

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteContentTransaction::TRANSACTIONTYPE)
      ->setNewValue($mail->getCleanTextBody());

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTitleTransaction::TRANSACTIONTYPE)
      ->setNewValue($title);

    $paste = PhabricatorPaste::initializeNewPaste($sender);

    $content_source = $mail->newContentSource();

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
