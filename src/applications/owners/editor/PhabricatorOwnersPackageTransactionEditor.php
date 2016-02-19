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
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_OWNERS;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_AUDITING;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_PATHS;
    $types[] = PhabricatorOwnersPackageTransaction::TYPE_STATUS;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
        $phids = mpull($object->getOwners(), 'getUserPHID');
        $phids = array_values($phids);
        return $phids;
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
        return (int)$object->getAuditingEnabled();
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        $paths = $object->getPaths();
        return mpull($paths, 'getRef');
      case PhabricatorOwnersPackageTransaction::TYPE_STATUS:
        return $object->getStatus();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
      case PhabricatorOwnersPackageTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        $new = $xaction->getNewValue();
        foreach ($new as $key => $info) {
          $new[$key]['excluded'] = (int)idx($info, 'excluded');
        }
        return $new;
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
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
        $object->setAuditingEnabled($xaction->getNewValue());
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOwnersPackageTransaction::TYPE_NAME:
      case PhabricatorOwnersPackageTransaction::TYPE_DESCRIPTION:
      case PhabricatorOwnersPackageTransaction::TYPE_AUDITING:
      case PhabricatorOwnersPackageTransaction::TYPE_STATUS:
        return;
      case PhabricatorOwnersPackageTransaction::TYPE_OWNERS:
        $old = $xaction->getOldValue();
        $new = $xaction->getNewValue();

        $owners = $object->getOwners();
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

        $paths = $object->getPaths();

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
      case PhabricatorOwnersPackageTransaction::TYPE_PATHS:
        if (!$xactions) {
          continue;
        }

        $old = mpull($object->getPaths(), 'getRef');
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();

          // Check that we have a list of paths.
          if (!is_array($new)) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('Path specification must be a list of paths.'),
              $xaction);
            continue;
          }

          // Check that each item in the list is formatted properly.
          $type_exception = null;
          foreach ($new as $key => $value) {
            try {
              PhutilTypeSpec::checkMap(
                $value,
                array(
                  'repositoryPHID' => 'string',
                  'path' => 'string',
                  'excluded' => 'optional wild',
                ));
            } catch (PhutilTypeCheckException $ex) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'Path specification list contains invalid value '.
                  'in key "%s": %s.',
                  $key,
                  $ex->getMessage()),
                $xaction);
              $type_exception = $ex;
            }
          }

          if ($type_exception) {
            continue;
          }

          // Check that any new paths reference legitimate repositories which
          // the viewer has permission to see.
          list($rem, $add) = PhabricatorOwnersPath::getTransactionValueChanges(
            $old,
            $new);

          if ($add) {
            $repository_phids = ipull($add, 'repositoryPHID');

            $repositories = id(new PhabricatorRepositoryQuery())
              ->setViewer($this->getActor())
              ->withPHIDs($repository_phids)
              ->execute();
            $repositories = mpull($repositories, null, 'getPHID');

            foreach ($add as $ref) {
              $repository_phid = $ref['repositoryPHID'];
              if (isset($repositories[$repository_phid])) {
                continue;
              }

              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'Path specification list references repository PHID "%s", '.
                  'but that is not a valid, visible repository.',
                  $repository_phid));
            }
          }
        }
        break;
    }

    return $errors;
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
      $this->requireActor()->getPHID(),
    );
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return mpull($object->getOwners(), 'getUserPHID');
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

  protected function supportsSearch() {
    return true;
  }

}
