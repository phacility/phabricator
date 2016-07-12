<?php

final class AlmanacDeviceTransaction
  extends AlmanacTransaction {

  const TYPE_NAME = 'almanac:device:name';
  const TYPE_INTERFACE = 'almanac:device:interface';

  public function getApplicationName() {
    return 'almanac';
  }

  public function getApplicationTransactionType() {
    return AlmanacDevicePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_INTERFACE:
        if ($old) {
          $phids[] = $old['networkPHID'];
        }
        if ($new) {
          $phids[] = $new['networkPHID'];
        }
        break;
    }

    return $phids;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this device.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this device from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_INTERFACE:
        if ($old && $new) {
          return pht(
            '%s changed interface %s on this device to %s.',
            $this->renderHandleLink($author_phid),
            $this->describeInterface($old),
            $this->describeInterface($new));
        } else if ($old) {
          return pht(
            '%s removed the interface %s from this device.',
            $this->renderHandleLink($author_phid),
            $this->describeInterface($old));
        } else if ($new) {
          return pht(
            '%s added the interface %s to this device.',
            $this->renderHandleLink($author_phid),
            $this->describeInterface($new));
        }
    }

    return parent::getTitle();
  }

  public function shouldGenerateOldValue() {
    switch ($this->getTransactionType()) {
      case self::TYPE_INTERFACE:
        return false;
    }
    return parent::shouldGenerateOldValue();
  }

  private function describeInterface(array $info) {
    return pht(
      '%s:%s (%s)',
      $info['address'],
      $info['port'],
      $this->renderHandleLink($info['networkPHID']));
  }

}
