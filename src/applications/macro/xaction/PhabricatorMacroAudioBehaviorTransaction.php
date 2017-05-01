<?php

final class PhabricatorMacroAudioBehaviorTransaction
  extends PhabricatorMacroTransactionType {

  const TRANSACTIONTYPE = 'macro:audiobehavior';

  public function generateOldValue($object) {
    return $object->getAudioBehavior();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAudioBehavior($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    $old = $this->getOldValue();
    switch ($new) {
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_ONCE:
        return pht(
          '%s set the audio to play once.',
          $this->renderAuthor());
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
        return pht(
          '%s set the audio to loop.',
          $this->renderAuthor());
      default:
        return pht(
          '%s disabled the audio for this macro.',
          $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    $old = $this->getOldValue();
    switch ($new) {
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_ONCE:
        return pht(
          '%s set the audio for %s to play once.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
        return pht(
          '%s set the audio for %s to loop.',
          $this->renderAuthor(),
          $this->renderObject());
      default:
        return pht(
          '%s disabled the audio for %s.',
          $this->renderAuthor(),
          $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    switch ($new) {
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_ONCE:
        return 'fa-play-circle';
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
        return 'fa-repeat';
      default:
        return 'fa-pause-circle';
    }
  }

}
