<?php

final class PhabricatorUserUsernameTransaction
  extends PhabricatorUserTransactionType {

  const TRANSACTIONTYPE = 'user.rename';

  public function generateOldValue($object) {
    return $object->getUsername();
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setUsername($value);
  }

  public function applyExternalEffects($object, $value) {
    $user = $object;

    $this->newUserLog(PhabricatorUserLog::ACTION_CHANGE_USERNAME)
      ->setOldValue($this->getOldValue())
      ->setNewValue($value)
      ->save();

    // The SSH key cache currently includes usernames, so dirty it. See T12554
    // for discussion.
    PhabricatorAuthSSHKeyQuery::deleteSSHKeyCache();

    $user->sendUsernameChangeEmail($this->getActor(), $this->getOldValue());
  }

  public function getTitle() {
    return pht(
      '%s renamed this user from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      $old = $xaction->getOldValue();

      if ($old === $new) {
        continue;
      }

      if (!$actor->getIsAdmin()) {
        $errors[] = $this->newInvalidError(
          pht('You must be an administrator to rename users.'));
      }

      if (!strlen($new)) {
        $errors[] = $this->newRequiredError(
          pht('New username is required.'),  $xaction);
      } else if (!PhabricatorUser::validateUsername($new)) {
        $errors[] = $this->newInvalidError(
          PhabricatorUser::describeValidUsername(), $xaction);
      }

      $user = id(new PhabricatorPeopleQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withUsernames(array($new))
        ->executeOne();

      if ($user) {
        $errors[] = $this->newInvalidError(
          pht('Another user already has that username.'), $xaction);
      }

    }

    return $errors;
  }

  public function getRequiredCapabilities(
    $object,
    PhabricatorApplicationTransaction $xaction) {

    // Unlike normal user edits, renames require admin permissions, which
    // is enforced by validateTransactions().

    return null;
  }

  public function shouldTryMFA(
    $object,
    PhabricatorApplicationTransaction $xaction) {
    return true;
  }

}
