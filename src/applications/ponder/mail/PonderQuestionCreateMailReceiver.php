<?php

final class PonderQuestionCreateMailReceiver
  extends PhabricatorApplicationMailReceiver {

  protected function newApplication() {
    return new PhabricatorPonderApplication();
  }

  protected function processReceivedMail(
    PhabricatorMetaMTAReceivedMail $mail,
    PhutilEmailAddress $target) {
    $author = $this->getAuthor();

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

    $question = PonderQuestion::initializeNewQuestion($author);

    $content_source = $mail->newContentSource();

    $editor = id(new PonderQuestionEditor())
      ->setActor($author)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true);
    $xactions = $editor->applyTransactions($question, $xactions);

    $mail->setRelatedPHID($question->getPHID());
  }


}
