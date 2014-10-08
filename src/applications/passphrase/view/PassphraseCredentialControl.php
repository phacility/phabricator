<?php

final class PassphraseCredentialControl extends AphrontFormControl {

  private $options = array();
  private $credentialType;
  private $defaultUsername;
  private $allowNull;

  public function setAllowNull($allow_null) {
    $this->allowNull = $allow_null;
    return $this;
  }

  public function setDefaultUsername($default_username) {
    $this->defaultUsername = $default_username;
    return $this;
  }

  public function setCredentialType($credential_type) {
    $this->credentialType = $credential_type;
    return $this;
  }

  public function getCredentialType() {
    return $this->credentialType;
  }

  public function setOptions(array $options) {
    assert_instances_of($options, 'PassphraseCredential');
    $this->options = $options;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'passphrase-credential-control';
  }

  protected function renderInput() {

    $options_map = array();
    foreach ($this->options as $option) {
      $options_map[$option->getPHID()] = pht(
        '%s %s',
        'K'.$option->getID(),
        $option->getName());
    }

    $disabled = $this->getDisabled();
    if ($this->allowNull) {
      $options_map = array('' => pht('(No Credentials)')) + $options_map;
    } else {
      if (!$options_map) {
        $options_map[''] = pht('(No Existing Credentials)');
        $disabled = true;
      }
    }

    Javelin::initBehavior('passphrase-credential-control');

    $options = AphrontFormSelectControl::renderSelectTag(
      $this->getValue(),
      $options_map,
      array(
        'id' => $this->getControlID(),
        'name' => $this->getName(),
        'disabled' => $disabled ? 'disabled' : null,
        'sigil' => 'passphrase-credential-select',
      ));

    if ($this->credentialType) {
      $button = javelin_tag(
        'a',
        array(
          'href' => '#',
          'class' => 'button grey',
          'sigil' => 'passphrase-credential-add',
          'mustcapture' => true,
        ),
        pht('Add Credential'));
    } else {
      $button = null;
    }

    return javelin_tag(
      'div',
      array(
        'sigil' => 'passphrase-credential-control',
        'meta' => array(
          'type' => $this->getCredentialType(),
          'username' => $this->defaultUsername,
          'allowNull' => $this->allowNull,
        ),
      ),
      array(
        $options,
        $button,
      ));
  }

  /**
   * Verify that a given actor has permission to use all of the credentials
   * in a list of credential transactions.
   *
   * In general, the rule here is:
   *
   *   - If you're editing an object and it uses a credential you can't use,
   *     that's fine as long as you don't change the credential.
   *   - If you do change the credential, the new credential must be one you
   *     can use.
   *
   * @param PhabricatorUser The acting user.
   * @param list<PhabricatorApplicationTransaction> List of credential altering
   *        transactions.
   * @return bool True if the transactions are valid.
   */
  public static function validateTransactions(
    PhabricatorUser $actor,
    array $xactions) {

    $new_phids = array();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      if (!$new) {
        // Removing a credential, so this is OK.
        continue;
      }

      $old = $xaction->getOldValue();
      if ($old == $new) {
        // This is a no-op transaction, so this is also OK.
        continue;
      }

      // Otherwise, we need to check this credential.
      $new_phids[] = $new;
    }

    if (!$new_phids) {
      // No new credentials being set, so this is fine.
      return true;
    }

    $usable_credentials = id(new PassphraseCredentialQuery())
      ->setViewer($actor)
      ->withPHIDs($new_phids)
      ->execute();
    $usable_credentials = mpull($usable_credentials, null, 'getPHID');

    foreach ($new_phids as $phid) {
      if (empty($usable_credentials[$phid])) {
        return false;
      }
    }

    return true;
  }


}
