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

    $file = PhabricatorPasteEditor::initializeFileForPaste(
      $sender,
      $title,
      $mail->getCleanTextBody());

    $xactions = array();

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_CONTENT)
      ->setNewValue($file->getPHID());

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_TITLE)
      ->setNewValue($title);

    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorPasteTransaction::TYPE_LANGUAGE)
      ->setNewValue(''); // auto-detect

    $paste = PhabricatorPaste::initializeNewPaste($sender);

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
