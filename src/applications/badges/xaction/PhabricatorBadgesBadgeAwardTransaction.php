<?php

final class PhabricatorBadgesBadgeAwardTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.award';

  public function generateOldValue($object) {
    return mpull($object->getAwards(), 'getRecipientPHID');
  }

  public function applyExternalEffects($object, $value) {
    $awards = $object->getAwards();
    $awards = mpull($awards, null, 'getRecipientPHID');

    foreach ($value as $phid) {
      $award = idx($awards, $phid);
      if (!$award) {
        $award = PhabricatorBadgesAward::initializeNewBadgesAward(
          $this->getActor(),
          $object,
          $phid);
        $award->save();
        $awards[] = $award;
      }
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
      '%s awarded this badge to %s recipient(s): %s.',
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
      '%s awarded %s to %s recipient(s): %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      new PhutilNumber(count($new)),
      $handles);
  }

  public function getIcon() {
    return 'fa-user-plus';
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $user_phids = $xaction->getNewValue();
      if (!$user_phids) {
        $errors[] = $this->newRequiredError(
          pht('Recipient is required.'));
        continue;
      }

      foreach ($user_phids as $user_phid) {
        $user = id(new PhabricatorPeopleQuery())
          ->setViewer($this->getActor())
          ->withPHIDs(array($user_phid))
          ->executeOne();
        if (!$user) {
          $errors[] = $this->newInvalidError(
            pht(
              'Recipient PHID "%s" is not a valid user PHID.',
              $user_phid));
        }
      }
    }

    return $errors;
  }

}
