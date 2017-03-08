<?php

final class PhabricatorBadgesBadgeAwardTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.award';

  public function generateOldValue($object) {
    return null;
  }

  public function applyExternalEffects($object, $value) {
    foreach ($value as $phid) {
      $award = PhabricatorBadgesAward::initializeNewBadgesAward(
        $this->getActor(),
        $object,
        $phid);
      $award->save();
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
        // Check if a valid user
        $user = id(new PhabricatorPeopleQuery())
          ->setViewer($this->getActor())
          ->withPHIDs(array($user_phid))
          ->executeOne();
        if (!$user) {
          $errors[] = $this->newInvalidError(
            pht(
              'Recipient PHID "%s" is not a valid user PHID.',
              $user_phid));
          continue;
        }

        // Check if already awarded
        $award = id(new PhabricatorBadgesAwardQuery())
          ->setViewer($this->getActor())
          ->withRecipientPHIDs(array($user_phid))
          ->withBadgePHIDs(array($object->getPHID()))
          ->executeOne();
        if ($award) {
          $errors[] = $this->newInvalidError(
            pht(
              '%s has already been awarded this badge.',
              $user->getUsername()));
        }
      }
    }

    return $errors;
  }

}
