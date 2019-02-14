<?php

final class PasteCreateMailReceiver
  extends PhabricatorApplicationMailReceiver {

  protected function newApplication() {
    return new PhabricatorPasteApplication();
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target) {
    $author = $this->getAuthor();

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

    $paste = PhabricatorPaste::initializeNewPaste($author);

    $content_source = $mail->newContentSource();

    $editor = id(new PhabricatorPasteEditor())
      ->setActor($author)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true);
    $xactions = $editor->applyTransactions($paste, $xactions);

    $mail->setRelatedPHID($paste->getPHID());

    $sender = $this->getSender();
    if (!$sender) {
      return;
    }

    $subject_prefix = pht('[Paste]');
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
