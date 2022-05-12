<?php

final class PhabricatorPasteContentTransaction
  extends PhabricatorPasteTransactionType {

  const TRANSACTIONTYPE = 'paste.create';

  private $filePHID;

  public function generateOldValue($object) {
    return $object->getFilePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFilePHID($value);
  }

  public function extractFilePHIDs($object, $value) {
    $file_phid = $this->getFilePHID($object, $value);
    return array($file_phid);
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

  public function generateNewValue($object, $value) {
    return $this->getFilePHID($object, $value);
  }

  private function getFilePHID($object, $value) {
    if ($this->filePHID === null) {
      $this->filePHID = $this->newFilePHID($object, $value);
    }

    return $this->filePHID;
  }

  private function newFilePHID($object, $value) {
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

    $file = $this->newFileForPaste($actor, $value);

    return $file->getPHID();
  }

  private function newFileForPaste(PhabricatorUser $actor, $data) {
    $editor = $this->getEditor();

    $file_name = $editor->getNewPasteTitle();
    if (!strlen($file_name)) {
      $file_name = 'raw-paste-data.txt';
    }

    return PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $file_name,
        'mime-type' => 'text/plain; charset=utf-8',
        'authorPHID' => $actor->getPHID(),
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
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

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO PASTE CONTENT');
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
