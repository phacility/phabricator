<?php

/**
 * @group paste
 */
final class PhabricatorPasteEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorPasteTransaction::TYPE_CREATE;
    $types[] = PhabricatorPasteTransaction::TYPE_TITLE;
    $types[] = PhabricatorPasteTransaction::TYPE_LANGUAGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CREATE:
        return null;
      case PhabricatorPasteTransaction::TYPE_TITLE:
        return $object->getTitle();
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        return $object->getLanguage();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_CREATE:
        // this was set via applyInitialEffects
        return $object->getFilePHID();
      case PhabricatorPasteTransaction::TYPE_TITLE:
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPasteTransaction::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        break;
      case PhabricatorPasteTransaction::TYPE_LANGUAGE:
        $object->setLanguage($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
  }


  protected function shouldApplyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() ==
          PhabricatorPasteTransaction::TYPE_CREATE) {
        return true;
      }
    }
    return false;
  }

  protected function applyInitialEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    foreach ($xactions as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PhabricatorPasteTransaction::TYPE_CREATE:
          $data = $xaction->getNewValue();
          $paste_file = PhabricatorFile::newFromFileData(
            $data['text'],
            array(
              'name' => $data['title'],
              'mime-type' => 'text/plain; charset=utf-8',
              'authorPHID' => $this->getActor()->getPHID(),
            ));
          $object->setFilePHID($paste_file->getPHID());
          break;
      }
    }
  }

  protected function supportsMail() {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.paste.subject-prefix');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PasteReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("P{$id}: {$name}")
      ->addHeader('Thread-Topic', "P{$id}");
  }

  protected function supportsFeed() {
    return true;
  }

  protected function supportsSearch() {
    return false;
  }

}
