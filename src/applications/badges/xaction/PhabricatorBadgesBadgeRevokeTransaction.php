<?php

final class PhabricatorBadgesBadgeRevokeTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.revoke';

  public function generateOldValue($object) {
    return null;
  }

  public function applyExternalEffects($object, $value) {
    $awards = $object->getAwards();
    $awards = mpull($awards, null, 'getRecipientPHID');

    foreach ($value as $phid) {
      $awards[$phid]->delete();
    }
    $object->attachAwards($awards);
    return;
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if (!is_array($new)) {
      $new = array();
    }
    $handles = $this->renderHandleList($new);
    return pht(
      '%s revoked this badge from %s recipient(s): %s.',
      $this->renderAuthor(),
      new PhutilNumber(count($new)),
      $handles);
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if (!is_array($new)) {
      $new = array();
    }
    $handles = $this->renderHandleList($new);
    return pht(
      '%s revoked %s from %s recipient(s): %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      new PhutilNumber(count($new)),
      $handles);
  }

  public function getIcon() {
    return 'fa-user-times';
  }

}
