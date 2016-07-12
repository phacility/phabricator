<?php

final class PhabricatorPasteContentTransaction
  extends PhabricatorPasteTransactionType {

  const TRANSACTIONTYPE = 'paste.create';

  private $fileName;

  public function generateOldValue($object) {
    return $object->getFilePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFilePHID($value);
  }

  public function extractFilePHIDs($object, $value) {
    return array($value);
  }

  public function validateTransactions($object, array $xactions) {
    if ($object->getFilePHID() || $xactions) {
      return array();
    }

    $error = $this->newError(
      pht('Required'),
      pht('You must provide content to create a paste.'));
    $error->setIsMissingFieldError(true);

    return array($error);
  }

  public function willApplyTransactions($object, array $xactions) {
    // Find the most user-friendly filename we can by examining the title of
    // the paste and the pending transactions. We'll use this if we create a
    // new file to store raw content later.

    $name = $object->getTitle();
    if (!strlen($name)) {
      $name = 'paste.raw';
    }

    $type_title = PhabricatorPasteTitleTransaction::TRANSACTIONTYPE;
    foreach ($xactions as $xaction) {
      if ($xaction->getTransactionType() == $type_title) {
        $name = $xaction->getNewValue();
      }
    }

    $this->fileName = $name;
  }

  public function generateNewValue($object, $value) {
    // If this transaction does not really change the paste content, return
    // the current file PHID so this transaction no-ops.
    $old_content = $object->getRawContent();
    if ($value === $old_content) {
      $file_phid = $object->getFilePHID();
      if ($file_phid) {
        return $file_phid;
      }
    }

    $editor = $this->getEditor();
    $actor = $editor->getActor();

    $file = $this->newFileForPaste($actor, $this->fileName, $value);

    return $file->getPHID();
  }

  private  function newFileForPaste(PhabricatorUser $actor, $name, $data) {
    return PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $name,
        'mime-type' => 'text/plain; charset=utf-8',
        'authorPHID' => $actor->getPHID(),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
        'editPolicy' => PhabricatorPolicies::POLICY_NOONE,
      ));
  }

  public function getIcon() {
    return 'fa-plus';
  }

  public function getTitle() {
    return pht(
      '%s edited the content of this paste.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s edited %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($old, $new))
      ->execute();
    $files = mpull($files, null, 'getPHID');

    $old_text = '';
    if (idx($files, $old)) {
      $old_text = $files[$old]->loadFileData();
    }

    $new_text = '';
    if (idx($files, $new)) {
      $new_text = $files[$new]->loadFileData();
    }

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($old_text)
      ->setNewText($new_text);
  }

}
