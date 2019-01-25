<?php

final class PhabricatorAuthFactorProviderStatusTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $old_display = PhabricatorAuthFactorProviderStatus::newForStatus($old)
      ->getName();
    $new_display = PhabricatorAuthFactorProviderStatus::newForStatus($new)
      ->getName();

    return pht(
      '%s changed the status of this provider from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_display),
      $this->renderValue($new_display));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $actor = $this->getActor();

    $map = PhabricatorAuthFactorProviderStatus::getMap();
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (!isset($map[$new_value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Status "%s" is invalid. Valid statuses are: %s.',
            $new_value,
            implode(', ', array_keys($map))),
          $xaction);
        continue;
      }

      $require_key = 'security.require-multi-factor-auth';
      $require_mfa = PhabricatorEnv::getEnvConfig($require_key);

      if ($require_mfa) {
        $status_active = PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE;
        if ($new_value !== $status_active) {
          $active_providers = id(new PhabricatorAuthFactorProviderQuery())
            ->setViewer($actor)
            ->withStatuses(
              array(
                $status_active,
              ))
            ->execute();
          $active_providers = mpull($active_providers, null, 'getID');
          unset($active_providers[$object->getID()]);

          if (!$active_providers) {
            $errors[] = $this->newInvalidError(
              pht(
                'You can not deprecate or disable the last active MFA '.
                'provider while "%s" is enabled, because new users would '.
                'be unable to enroll in MFA. Disable the MFA requirement '.
                'in Config, or create or enable another MFA provider first.',
                $require_key));
            continue;
          }
        }
      }
    }

    return $errors;
  }

  public function didCommitTransaction($object, $value) {
    $status = PhabricatorAuthFactorProviderStatus::newForStatus($value);

    // If a provider has undergone a status change, reset the MFA enrollment
    // cache for all users. This may immediately force a lot of users to redo
    // MFA enrollment.

    // We could be more surgical about this: we only really need to affect
    // users who had a factor under the provider, and only really need to
    // do anything if a provider was disabled. This is just a little simpler.

    $table = new PhabricatorUser();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'UPDATE %R SET isEnrolledInMultiFactor = 0',
      $table);
  }

}
