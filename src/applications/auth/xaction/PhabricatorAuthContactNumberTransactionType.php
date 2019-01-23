<?php

abstract class PhabricatorAuthContactNumberTransactionType
  extends PhabricatorModularTransactionType {

  protected function newContactNumberMFAError($object, $xaction) {
    // If a contact number is attached to a user and that user has SMS MFA
    // configured, don't let the user modify their primary contact number or
    // make another contact number into their primary number.

    $primary_type =
      PhabricatorAuthContactNumberPrimaryTransaction::TRANSACTIONTYPE;

    if ($xaction->getTransactionType() === $primary_type) {
      // We're trying to make a non-primary number into the primary number,
      // so do MFA checks.
      $is_primary = false;
    } else if ($object->getIsPrimary()) {
      // We're editing the primary number, so do MFA checks.
      $is_primary = true;
    } else {
      // Editing a non-primary number and not making it primary, so this is
      // fine.
      return null;
    }

    $target_phid = $object->getObjectPHID();
    $omnipotent = PhabricatorUser::getOmnipotentUser();

    $user_configs = id(new PhabricatorAuthFactorConfigQuery())
      ->setViewer($omnipotent)
      ->withUserPHIDs(array($target_phid))
      ->execute();

    $problem_configs = array();
    foreach ($user_configs as $config) {
      $provider = $config->getFactorProvider();
      $factor = $provider->getFactor();

      if ($factor->isContactNumberFactor()) {
        $problem_configs[] = $config;
      }
    }

    if (!$problem_configs) {
      return null;
    }

    $problem_config = head($problem_configs);

    if ($is_primary) {
      return $this->newInvalidError(
        pht(
          'You currently have multi-factor authentication ("%s") which '.
          'depends on your primary contact number. You must remove this '.
          'authentication factor before you can modify or disable your '.
          'primary contact number.',
          $problem_config->getFactorName()),
        $xaction);
    } else {
      return $this->newInvalidError(
        pht(
          'You currently have multi-factor authentication ("%s") which '.
          'depends on your primary contact number. You must remove this '.
          'authentication factor before you can designate a new primary '.
          'contact number.',
          $problem_config->getFactorName()),
        $xaction);
    }
  }

}
