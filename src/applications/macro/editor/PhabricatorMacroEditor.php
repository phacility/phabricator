<?php

final class PhabricatorMacroEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorMacroApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Macros');
  }

  public function getCreateObjectTitle($author, $object) {
    return pht('%s created this macro.', $author);
  }

  public function getCreateObjectTitleForFeed($author, $object) {
    return pht('%s created %s.', $author, $object);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMacroFileTransaction::TRANSACTIONTYPE:
      case PhabricatorMacroAudioTransaction::TRANSACTIONTYPE:
        // When changing a macro's image or audio, attach the underlying files
        // to the macro (and detach the old files).
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();
        $all = array();
        if ($old) {
          $all[] = $old;
        }
        if ($new) {
          $all[] = $new;
        }

        $files = id(new PhabricatorFileQuery())
          ->setViewer($this->requireActor())
          ->withPHIDs($all)
          ->execute();
        $files = mpull($files, null, 'getPHID');

        $old_file = idx($files, $old);
        if ($old_file) {
          $old_file->detachFromObject($object->getPHID());
        }

        $new_file = idx($files, $new);
        if ($new_file) {
          $new_file->attachToObject($object->getPHID());
        }
        break;
    }
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorMacroReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $name = $object->getName();
    $name = 'Image Macro "'.$name.'"';

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $name);
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $body->addLinkSection(
      pht('MACRO DETAIL'),
      PhabricatorEnv::getProductionURI('/macro/view/'.$object->getID().'/'));

    return $body;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.macro.subject-prefix');
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }
}
