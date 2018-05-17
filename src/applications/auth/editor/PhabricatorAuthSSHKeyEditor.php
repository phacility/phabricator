<?php

final class PhabricatorAuthSSHKeyEditor
  extends PhabricatorApplicationTransactionEditor {

  private $isAdministrativeEdit;

  public function setIsAdministrativeEdit($is_administrative_edit) {
    $this->isAdministrativeEdit = $is_administrative_edit;
    return $this;
  }

  public function getIsAdministrativeEdit() {
    return $this->isAdministrativeEdit;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('SSH Keys');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorAuthSSHKeyTransaction::TYPE_NAME;
    $types[] = PhabricatorAuthSSHKeyTransaction::TYPE_KEY;
    $types[] = PhabricatorAuthSSHKeyTransaction::TYPE_DEACTIVATE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthSSHKeyTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorAuthSSHKeyTransaction::TYPE_KEY:
        return $object->getEntireKey();
      case PhabricatorAuthSSHKeyTransaction::TYPE_DEACTIVATE:
        return !$object->getIsActive();
    }

  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthSSHKeyTransaction::TYPE_NAME:
      case PhabricatorAuthSSHKeyTransaction::TYPE_KEY:
        return $xaction->getNewValue();
      case PhabricatorAuthSSHKeyTransaction::TYPE_DEACTIVATE:
        return (bool)$xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $value = $xaction->getNewValue();
    switch ($xaction->getTransactionType()) {
      case PhabricatorAuthSSHKeyTransaction::TYPE_NAME:
        $object->setName($value);
        return;
      case PhabricatorAuthSSHKeyTransaction::TYPE_KEY:
        $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($value);

        $type = $public_key->getType();
        $body = $public_key->getBody();
        $comment = $public_key->getComment();

        $object->setKeyType($type);
        $object->setKeyBody($body);
        $object->setKeyComment($comment);
        return;
      case PhabricatorAuthSSHKeyTransaction::TYPE_DEACTIVATE:
        if ($value) {
          $new = null;
        } else {
          $new = 1;
        }

        $object->setIsActive($new);
        return;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);
    $viewer = $this->requireActor();

    switch ($type) {
      case PhabricatorAuthSSHKeyTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('SSH key name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;

      case PhabricatorAuthSSHKeyTransaction::TYPE_KEY;
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('SSH key material is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        } else {
          foreach ($xactions as $xaction) {
            $new = $xaction->getNewValue();

            try {
              $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($new);
            } catch (Exception $ex) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                $ex->getMessage(),
                $xaction);
              continue;
            }

            // The database does not have a unique key on just the <keyBody>
            // column because we allow multiple accounts to revoke the same
            // key, so we can't rely on database constraints to prevent users
            // from adding keys that are on the revocation list back to their
            // accounts. Explicitly check for a revoked copy of the key.

            $revoked_keys = id(new PhabricatorAuthSSHKeyQuery())
              ->setViewer($viewer)
              ->withObjectPHIDs(array($object->getObjectPHID()))
              ->withIsActive(0)
              ->withKeys(array($public_key))
              ->execute();
            if ($revoked_keys) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Revoked'),
                pht(
                  'This key has been revoked. Choose or generate a new, '.
                  'unique key.'),
                $xaction);
              continue;
            }
          }
        }
        break;

      case PhabricatorAuthSSHKeyTransaction::TYPE_DEACTIVATE:
        foreach ($xactions as $xaction) {
          if (!$xaction->getNewValue()) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('SSH keys can not be reactivated.'),
              $xaction);
          }
        }
        break;
    }

    return $errors;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();
    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhabricatorAuthSSHKeyTransaction::TYPE_KEY,
      pht('Duplicate'),
      pht(
        'This public key is already associated with another user or device. '.
        'Each key must unambiguously identify a single unique owner.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }


  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[SSH Key]');
  }

  protected function getMailThreadID(PhabricatorLiskDAO $object) {
    return 'ssh-key-'.$object->getPHID();
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // After making any change to an SSH key, drop the authfile cache so it
    // is regenerated the next time anyone authenticates.
    PhabricatorAuthSSHKeyQuery::deleteSSHKeyCache();

    return $xactions;
  }


  protected function getMailTo(PhabricatorLiskDAO $object) {
    return $object->getObject()->getSSHKeyNotifyPHIDs();
  }

  protected function getMailCC(PhabricatorLiskDAO $object) {
    return array();
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorAuthSSHKeyReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    $mail = id(new PhabricatorMetaMTAMail())
      ->setSubject(pht('SSH Key %d: %s', $id, $name));

    // The primary value of this mail is alerting users to account compromises,
    // so force delivery. In particular, this mail should still be delivered
    // even if "self mail" is disabled.
    $mail->setForceDelivery(true);

    return $mail;
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    if (!$this->getIsAdministrativeEdit()) {
      $body->addTextSection(
        pht('SECURITY WARNING'),
        pht(
          'If you do not recognize this change, it may indicate your account '.
          'has been compromised.'));
    }

    $detail_uri = $object->getURI();
    $detail_uri = PhabricatorEnv::getProductionURI($detail_uri);

    $body->addLinkSection(pht('SSH KEY DETAIL'), $detail_uri);

    return $body;
  }


  protected function getCustomWorkerState() {
    return array(
      'isAdministrativeEdit' => $this->isAdministrativeEdit,
    );
  }

  protected function loadCustomWorkerState(array $state) {
    $this->isAdministrativeEdit = idx($state, 'isAdministrativeEdit');
    return $this;
  }


}
