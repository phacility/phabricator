<?php

final class PhabricatorMacroAudioTransaction
  extends PhabricatorMacroTransactionType {

  const TRANSACTIONTYPE = 'macro:audio';

  public function generateOldValue($object) {
    return $object->getAudioPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAudioPHID($value);
  }

  public function applyExternalEffects($object, $value) {
    $old = $this->generateOldValue($object);
    $new = $value;
    $all = array();
    if ($old) {
      $all[] = $old;
    }
    if ($new) {
      $all[] = $new;
    }

    $files = id(new PhabricatorFileQuery())
      ->setViewer($this->getActor())
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
  }

  public function getTitle() {
    $new = $this->getNewValue();
    $old = $this->getOldValue();
    if (!$old) {
      return pht(
        '%s attached audio: %s.',
        $this->renderAuthor(),
        $this->renderHandle($new));
    } else {
      return pht(
        '%s changed the audio for this macro from %s to %s.',
        $this->renderAuthor(),
        $this->renderHandle($old),
        $this->renderHandle($new));
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    $old = $this->getOldValue();
    if (!$old) {
      return pht(
        '%s attached audio to %s: %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderHandle($new));
    } else {
      return pht(
        '%s changed the audio for %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderHandle($old),
        $this->renderHandle($new));
    }
  }

  public function getIcon() {
    return 'fa-music';
  }

}
