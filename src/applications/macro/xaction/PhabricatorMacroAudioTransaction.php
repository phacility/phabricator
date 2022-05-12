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

  public function extractFilePHIDs($object, $value) {
    $file_phids = array();

    if ($value) {
      $file_phids[] = $value;
    }

    return $file_phids;
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
