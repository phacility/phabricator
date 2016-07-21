<?php

final class PhabricatorPackagesVersionPackageTransaction
  extends PhabricatorPackagesVersionTransactionType {

  const TRANSACTIONTYPE = 'packages.version.package';

  public function generateOldValue($object) {
    return $object->getPackagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPackagePHID($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getPackagePHID(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht(
          'You must select a package when creating a version'));
      return $errors;
    }

    if (!$this->isNewObject()) {
      foreach ($xactions as $xaction) {
        $errors[] = $this->newInvalidError(
          pht('Once a version is created, its package can not be changed.'),
          $xaction);
      }
    }

    $viewer = $this->getActor();
    foreach ($xactions as $xaction) {
      $package_phid = $xaction->getNewValue();

      $package = id(new PhabricatorPackagesPackageQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($package_phid))
        ->setRaisePolicyExceptions(false)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();

      if (!$package) {
        $errors[] = $this->newInvalidError(
          pht(
            'Package "%s" is invalid: the package must exist and you '.
            'must have permission to edit it in order to create a new '.
            'package.',
            $package_phid),
          $xaction);
        continue;
      }

      $object->attachPackage($package);
    }

    return $errors;
  }

}
