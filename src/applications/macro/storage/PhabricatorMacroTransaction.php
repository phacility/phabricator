<?php

final class PhabricatorMacroTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME       = 'macro:name';
  const TYPE_DISABLED   = 'macro:disabled';
  const TYPE_FILE       = 'macro:file';

  const TYPE_AUDIO = 'macro:audio';
  const TYPE_AUDIO_BEHAVIOR = 'macro:audiobehavior';

  public function getApplicationName() {
    return 'file';
  }

  public function getTableName() {
    return 'macro_transaction';
  }

  public function getApplicationTransactionType() {
    return PhabricatorMacroMacroPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorMacroTransactionComment();
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_FILE:
      case self::TYPE_AUDIO:
        if ($old !== null) {
          $phids[] = $old;
        }
        $phids[] = $new;
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return pht(
          '%s renamed this macro from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
        break;
      case self::TYPE_DISABLED:
        if ($new) {
          return pht(
            '%s disabled this macro.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s restored this macro.',
            $this->renderHandleLink($author_phid));
        }
        break;

      case self::TYPE_AUDIO:
        if (!$old) {
          return pht(
            '%s attached audio: %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s changed the audio for this macro from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_AUDIO_BEHAVIOR:
        switch ($new) {
          case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_ONCE:
            return pht(
              '%s set the audio to play once.',
              $this->renderHandleLink($author_phid));
          case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
            return pht(
              '%s set the audio to loop.',
              $this->renderHandleLink($author_phid));
          default:
            return pht(
              '%s disabled the audio for this macro.',
              $this->renderHandleLink($author_phid));
        }

      case self::TYPE_FILE:
        if ($old === null) {
          return pht(
            '%s created this macro.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the image for this macro from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
        break;
    }

    return parent::getTitle();
  }

  public function getTitleForFeed() {
    $author_phid = $this->getAuthorPHID();
    $object_phid = $this->getObjectPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return pht(
          '%s renamed %s from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($object_phid),
          $old,
          $new);
      case self::TYPE_DISABLED:
        if ($new) {
          return pht(
            '%s disabled %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s restored %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }
      case self::TYPE_FILE:
        if ($old === null) {
          return pht(
            '%s created %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        } else {
          return pht(
            '%s updated the image for %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid));
        }

      case self::TYPE_AUDIO:
        if (!$old) {
          return pht(
            '%s attached audio to %s: %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s changed the audio for %s from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($object_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }

      case self::TYPE_AUDIO_BEHAVIOR:
        switch ($new) {
          case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_ONCE:
            return pht(
              '%s set the audio for %s to play once.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
            return pht(
              '%s set the audio for %s to loop.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
          default:
            return pht(
              '%s disabled the audio for %s.',
              $this->renderHandleLink($author_phid),
              $this->renderHandleLink($object_phid));
        }

    }

    return parent::getTitleForFeed();
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht('Created');
        } else {
          return pht('Renamed');
        }
      case self::TYPE_DISABLED:
        if ($new) {
          return pht('Disabled');
        } else {
          return pht('Restored');
        }
      case self::TYPE_FILE:
        if ($old === null) {
          return pht('Created');
        } else {
          return pht('Edited Image');
        }

      case self::TYPE_AUDIO:
        return pht('Audio');

      case self::TYPE_AUDIO_BEHAVIOR:
        return pht('Audio Behavior');

    }

    return parent::getActionName();
  }

  public function getActionStrength() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DISABLED:
        return 2.0;
      case self::TYPE_FILE:
        return 1.5;
    }
    return parent::getActionStrength();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return 'fa-pencil';
      case self::TYPE_FILE:
        if ($old === null) {
          return 'fa-plus';
        } else {
          return 'fa-pencil';
        }
      case self::TYPE_DISABLED:
        if ($new) {
          return 'fa-times';
        } else {
          return 'fa-undo';
        }
      case self::TYPE_AUDIO:
        return 'fa-headphones';
    }

    return parent::getIcon();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        return PhabricatorTransactions::COLOR_BLUE;
      case self::TYPE_FILE:
        if ($old === null) {
          return PhabricatorTransactions::COLOR_GREEN;
        } else {
          return PhabricatorTransactions::COLOR_BLUE;
        }
      case self::TYPE_DISABLED:
        if ($new) {
          return PhabricatorTransactions::COLOR_RED;
        } else {
          return PhabricatorTransactions::COLOR_SKY;
        }
    }

    return parent::getColor();
  }


}
