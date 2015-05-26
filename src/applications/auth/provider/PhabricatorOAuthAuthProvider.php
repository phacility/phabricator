<?php

abstract class PhabricatorOAuthAuthProvider extends PhabricatorAuthProvider {

  const PROPERTY_NOTE = 'oauth:app:note';

  protected $adapter;

  abstract protected function newOAuthAdapter();
  abstract protected function getIDKey();
  abstract protected function getSecretKey();

  public function getDescriptionForCreate() {
    return pht('Configure %s OAuth.', $this->getProviderName());
  }

  public function getAdapter() {
    if (!$this->adapter) {
      $adapter = $this->newOAuthAdapter();
      $this->adapter = $adapter;
      $this->configureAdapter($adapter);
    }
    return $this->adapter;
  }

  public function isLoginFormAButton() {
    return true;
  }

  public function readFormValuesFromProvider() {
    $config = $this->getProviderConfig();
    $id = $config->getProperty($this->getIDKey());
    $secret = $config->getProperty($this->getSecretKey());
    $note = $config->getProperty(self::PROPERTY_NOTE);

    return array(
      $this->getIDKey()     => $id,
      $this->getSecretKey() => $secret,
      self::PROPERTY_NOTE   => $note,
    );
  }

  public function readFormValuesFromRequest(AphrontRequest $request) {
    return array(
      $this->getIDKey()     => $request->getStr($this->getIDKey()),
      $this->getSecretKey() => $request->getStr($this->getSecretKey()),
      self::PROPERTY_NOTE   => $request->getStr(self::PROPERTY_NOTE),
    );
  }

  protected function processOAuthEditForm(
    AphrontRequest $request,
    array $values,
    $id_error,
    $secret_error) {

    $errors = array();
    $issues = array();
    $key_id = $this->getIDKey();
    $key_secret = $this->getSecretKey();

    if (!strlen($values[$key_id])) {
      $errors[] = $id_error;
      $issues[$key_id] = pht('Required');
    }

    if (!strlen($values[$key_secret])) {
      $errors[] = $secret_error;
      $issues[$key_secret] = pht('Required');
    }

    // If the user has not changed the secret, don't update it (that is,
    // don't cause a bunch of "****" to be written to the database).
    if (preg_match('/^[*]+$/', $values[$key_secret])) {
      unset($values[$key_secret]);
    }

    return array($errors, $issues, $values);
  }

  public function getConfigurationHelp() {
    $help = $this->getProviderConfigurationHelp();

    return $help."\n\n".
      pht(
        'Use the **OAuth App Notes** field to record details about which '.
        'account the external application is registered under.');
  }

  abstract protected function getProviderConfigurationHelp();

  protected function extendOAuthEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues,
    $id_label,
    $secret_label) {

    $key_id = $this->getIDKey();
    $key_secret = $this->getSecretKey();
    $key_note = self::PROPERTY_NOTE;

    $v_id = $values[$key_id];
    $v_secret = $values[$key_secret];
    if ($v_secret) {
      $v_secret = str_repeat('*', strlen($v_secret));
    }
    $v_note = $values[$key_note];

    $e_id = idx($issues, $key_id, $request->isFormPost() ? null : true);
    $e_secret = idx($issues, $key_secret, $request->isFormPost() ? null : true);

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel($id_label)
          ->setName($key_id)
          ->setValue($v_id)
          ->setError($e_id))
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel($secret_label)
          ->setDisableAutocomplete(true)
          ->setName($key_secret)
          ->setValue($v_secret)
          ->setError($e_secret))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
        ->setLabel(pht('OAuth App Notes'))
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
        ->setName($key_note)
        ->setValue($v_note));
  }

  public function renderConfigPropertyTransactionTitle(
    PhabricatorAuthProviderConfigTransaction $xaction) {

    $author_phid = $xaction->getAuthorPHID();
    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    $key = $xaction->getMetadataValue(
      PhabricatorAuthProviderConfigTransaction::PROPERTY_KEY);

    switch ($key) {
      case self::PROPERTY_NOTE:
        if (strlen($old)) {
          return pht(
            '%s updated the OAuth application notes for this provider.',
            $xaction->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s set the OAuth application notes for this provider.',
            $xaction->renderHandleLink($author_phid));
        }

    }

    return parent::renderConfigPropertyTransactionTitle($xaction);
  }

  protected function willSaveAccount(PhabricatorExternalAccount $account) {
    parent::willSaveAccount($account);
    $this->synchronizeOAuthAccount($account);
  }

  abstract protected function synchronizeOAuthAccount(
    PhabricatorExternalAccount $account);

}
