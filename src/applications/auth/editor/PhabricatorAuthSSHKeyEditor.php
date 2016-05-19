<?php

final class PhabricatorAuthSSHKeyEditor
  extends PhabricatorApplicationTransactionEditor {

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
    $phid = $object->getPHID();

    $mail = id(new PhabricatorMetaMTAMail())
      ->setSubject(pht('SSH Key %d: %s', $id, $name))
      ->addHeader('Thread-Topic', $phid);

    // The primary value of this mail is alerting users to account compromises,
    // so force delivery. In particular, this mail should still be delievered
    // even if "self mail" is disabled.
    $mail->setForceDelivery(true);

    return $mail;
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addTextSection(
      pht('SECURITY WARNING'),
      pht(
        'If you do not recognize this change, it may indicate your account '.
        'has been compromised.'));

    $detail_uri = $object->getURI();
    $detail_uri = PhabricatorEnv::getProductionURI($detail_uri);

    $body->addLinkSection(pht('SSH KEY DETAIL'), $detail_uri);

    return $body;
  }

}
