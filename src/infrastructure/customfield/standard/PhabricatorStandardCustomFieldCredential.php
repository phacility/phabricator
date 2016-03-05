<?php

final class PhabricatorStandardCustomFieldCredential
  extends PhabricatorStandardCustomField {

  public function getFieldType() {
    return 'credential';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newStringIndex($value);
    }

    return $indexes;
  }

  public function renderEditControl(array $handles) {
    $provides_type = $this->getFieldConfigValue('credential.provides');
    $credential_type = $this->getFieldConfigValue('credential.type');

    $all_types = PassphraseCredentialType::getAllProvidesTypes();
    if (!in_array($provides_type, $all_types)) {
      $provides_type = PassphrasePasswordCredentialType::PROVIDES_TYPE;
    }

    $credentials = id(new PassphraseCredentialQuery())
      ->setViewer($this->getViewer())
      ->withIsDestroyed(false)
      ->withProvidesTypes(array($provides_type))
      ->execute();

    return id(new PassphraseCredentialControl())
      ->setLabel($this->getFieldName())
      ->setName($this->getFieldKey())
      ->setCaption($this->getCaption())
      ->setAllowNull(!$this->getRequired())
      ->setCredentialType($credential_type)
      ->setValue($this->getFieldValue())
      ->setError($this->getFieldError())
      ->setOptions($credentials);
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $value = $this->getFieldValue();
    if ($value) {
      return array($value);
    }
    return array();
  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();
    if ($value) {
      return $handles[$value]->renderLink();
    }
    return null;
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    $ok = PassphraseCredentialControl::validateTransactions(
      $this->getViewer(),
      $xactions);

    if (!$ok) {
      foreach ($xactions as $xaction) {
        $errors[] = new PhabricatorApplicationTransactionValidationError(
          $type,
          pht('Invalid'),
          pht(
            'The selected credential does not exist, or you do not have '.
            'permission to use it.'),
          $xaction);
        $this->setFieldError(pht('Invalid'));
      }
    }

    return $errors;
  }

  public function getApplicationTransactionRequiredHandlePHIDs(
    PhabricatorApplicationTransaction $xaction) {
    $phids = array();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    if ($old) {
      $phids[] = $old;
    }
    if ($new) {
      $phids[] = $new;
    }
    return $phids;
  }


  public function getApplicationTransactionTitle(
    PhabricatorApplicationTransaction $xaction) {
    $author_phid = $xaction->getAuthorPHID();

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    if ($old && !$new) {
      return pht(
        '%s removed %s as %s.',
        $xaction->renderHandleLink($author_phid),
        $xaction->renderHandleLink($old),
        $this->getFieldName());
    } else if ($new && !$old) {
      return pht(
        '%s set %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($new));
    } else {
      return pht(
        '%s changed %s from %s to %s.',
        $xaction->renderHandleLink($author_phid),
        $this->getFieldName(),
        $xaction->renderHandleLink($old),
        $xaction->renderHandleLink($new));
    }
  }


  protected function getHTTPParameterType() {
    return new AphrontPHIDHTTPParameterType();
  }

  protected function newConduitSearchParameterType() {
    return new ConduitPHIDParameterType();
  }

  protected function newConduitEditParameterType() {
    return new ConduitPHIDParameterType();
  }

}
