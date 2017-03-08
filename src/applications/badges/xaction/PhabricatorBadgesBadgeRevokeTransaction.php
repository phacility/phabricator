<?php

final class PhabricatorBadgesBadgeRevokeTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.revoke';

  public function generateOldValue($object) {
    return null;
  }

  public function applyExternalEffects($object, $value) {
    $awards = id(new PhabricatorBadgesAwardQuery())
      ->setViewer($this->getActor())
      ->withRecipientPHIDs($value)
      ->withBadgePHIDs(array($object->getPHID()))
      ->execute();
    $awards = mpull($awards, null, 'getRecipientPHID');

    foreach ($value as $phid) {
      $awards[$phid]->delete();
    }
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

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $award_phids = $xaction->getNewValue();
      if (!$award_phids) {
        $errors[] = $this->newRequiredError(
          pht('Recipient is required.'));
        continue;
      }

      foreach ($award_phids as $award_phid) {
        $award = id(new PhabricatorBadgesAwardQuery())
          ->setViewer($this->getActor())
          ->withRecipientPHIDs(array($award_phid))
          ->withBadgePHIDs(array($object->getPHID()))
          ->executeOne();
        if (!$award) {
          $errors[] = $this->newInvalidError(
            pht(
              'Recipient PHID "%s" has not been awarded.',
              $award_phid));
        }
      }
    }

    return $errors;
  }

}
