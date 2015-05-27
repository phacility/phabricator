<?php

final class PhabricatorOwnersPackageTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorOwnersApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Owners Packages');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorOwnersPackageTransaction::TYPE_NAME;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_PRIMARY;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_OWNERS;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_AUDITING;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_PATHS;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorOwnersPackageTransaction::TYPE_PRIMARY:
        return $object->getPrimaryOwnerPHID();
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
        // TODO: needOwners() this on the Query.
        $phids = mpull($object->loadOwners(), 'getUserPHID');
        $phids = array_values($phids);
        return $phids;
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
        return (int)$object->getAuditingEnabled();
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        // TODO: needPaths() this on the query
        $paths = $object->loadPaths();
        return mpull($paths, 'getRef');
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
      case PhabricatorOwnersPackageTransaction::TYPE_PRIMARY:
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        return $xaction->getNewValue();
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
        return (int)$xaction->getNewValue();
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
        $phids = $xaction->getNewValue();
        $phids = array_unique($phids);
        $phids = array_values($phids);
        return $phids;
    }
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        $diffs = PhabricatorOwnersPath::getTransactionValueChanges($old, $new);
        list($rem, $add) = $diffs;

        return ($rem || $add);
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_PRIMARY:
        $object->setPrimaryOwnerPHID($xaction->getNewValue());
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
        $object->setAuditingEnabled($xaction->getNewValue());
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
      case PhabricatorOwnersPackageTransaction::TYPE_PRIMARY:
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        // TODO: needOwners this
        $owners = $object->loadOwners();
        $owners = mpull($owners, null, 'getUserPHID');

        $rem = array_diff($old, $new);
        foreach ($rem as $phid) {
          if (isset($owners[$phid])) {
            $owners[$phid]->delete();
            unset($owners[$phid]);
          }
        }

        $add = array_diff($new, $old);
        foreach ($add as $phid) {
          $owners[$phid] = id(new PhabricatorOwnersOwner())
            ->setPackageID($object->getID())
            ->setUserPHID($phid)
            ->save();
        }

        // TODO: Attach owners here
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        // TODO: needPaths this
        $paths = $object->loadPaths();

        $diffs = PhabricatorOwnersPath::getTransactionValueChanges($old, $new);
        list($rem, $add) = $diffs;

        $set = PhabricatorOwnersPath::getSetFromTransactionValue($rem);
        foreach ($paths as $path) {
          $ref = $path->getRef();
          if (PhabricatorOwnersPath::isRefInSet($ref, $set)) {
            $path->delete();
          }
        }

        foreach ($add as $ref) {
          $path = PhabricatorOwnersPath::newFromRef($ref)
            ->setPackageID($object->getID())
            ->save();
        }

        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Package name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PhabricatorOwnersPackageTransaction::TYPE_PRIMARY:
        $missing = $this->validateIsEmptyTextField(
          $object->getPrimaryOwnerPHID(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Packages must have a primary owner.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
        return array($xaction->getNewValue());
    }

    return parent::extractFilePHIDsFromCustomTransaction($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.package.subject-prefix');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getPrimaryOwnerPHID(),
      $this->requireActor()->getPHID(),
    );
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    // TODO: needOwners() this
    return mpull($object->loadOwners(), 'getUserPHID');
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new OwnersPackageReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $object->getPHID());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $detail_uri = PhabricatorEnv::getProductionURI(
      '/owners/package/'.$object->getID().'/');

    $body->addLinkSection(
      pht('PACKAGE DETAIL'),
      $detail_uri);

    return $body;
  }

}
