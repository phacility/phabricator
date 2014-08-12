<?php

abstract class PonderEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPonderApplication';
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();
    $original_title = $object->getOriginalTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("Q{$id}: {$title}")
      ->addHeader('Thread-Topic', "Q{$id}: {$original_title}");
  }


  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

   protected function getMailSubjectPrefix() {
    return '[Ponder]';
  }

}
