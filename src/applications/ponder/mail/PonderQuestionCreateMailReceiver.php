<?php

final class PonderQuestionCreateMailReceiver extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorPonderApplication';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $ponder_app = new PhabricatorPonderApplication();
    return $this->canAcceptApplicationMail($ponder_app, $mail);
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhabricatorUser $sender) {

    $title = $mail->getSubject();
    if (!strlen($title)) {
      $title = pht('New Question');
    }

    $xactions = array();

    $xactions[] = id(new PonderQuestionTransaction())
      ->setTransactionType(PonderQuestionTransaction::TYPE_TITLE)
      ->setNewValue($title);

    $xactions[] = id(new PonderQuestionTransaction())
      ->setTransactionType(PonderQuestionTransaction::TYPE_CONTENT)
      ->setNewValue($mail->getCleanTextBody());

    $question = PonderQuestion::initializeNewQuestion($sender);

    $content_source = $mail->newContentSource();

    $editor = id(new PonderQuestionEditor())
      ->setActor($sender)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true);
    $xactions = $editor->applyTransactions($question, $xactions);

    $mail->setRelatedPHID($question->getPHID());

  }


}
