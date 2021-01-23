<?php

final class PhabricatorAuthPasswordEngine
  extends Phobject {

  private $viewer;
  private $contentSource;
  private $object;
  private $passwordType;
  private $upgradeHashers = true;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function setObject(PhabricatorAuthPasswordHashInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setPasswordType($password_type) {
    $this->passwordType = $password_type;
    return $this;
  }

  public function getPasswordType() {
    return $this->passwordType;
  }

  public function setUpgradeHashers($upgrade_hashers) {
    $this->upgradeHashers = $upgrade_hashers;
    return $this;
  }

  public function getUpgradeHashers() {
    return $this->upgradeHashers;
  }

  public function checkNewPassword(
    PhutilOpaqueEnvelope $password,
    PhutilOpaqueEnvelope $confirm,
    $can_skip = false) {

    $raw_password = $password->openEnvelope();

    if (!strlen($raw_password)) {
      if ($can_skip) {
        throw new PhabricatorAuthPasswordException(
          pht('You must choose a password or skip this step.'),
          pht('Required'));
      } else {
        throw new PhabricatorAuthPasswordException(
          pht('You must choose a password.'),
          pht('Required'));
      }
    }

    $min_len = PhabricatorEnv::getEnvConfig('account.minimum-password-length');
    $min_len = (int)$min_len;
    if ($min_len) {
      if (strlen($raw_password) < $min_len) {
        throw new PhabricatorAuthPasswordException(
          pht(
            'The selected password is too short. Passwords must be a minimum '.
            'of %s characters long.',
            new PhutilNumber($min_len)),
          pht('Too Short'));
      }
    }

    $raw_confirm = $confirm->openEnvelope();

    if (!strlen($raw_confirm)) {
      throw new PhabricatorAuthPasswordException(
        pht('You must confirm the selected password.'),
        null,
        pht('Required'));
    }

    if ($raw_password !== $raw_confirm) {
      throw new PhabricatorAuthPasswordException(
        pht('The password and confirmation do not match.'),
        pht('Invalid'),
        pht('Invalid'));
    }

    if (PhabricatorCommonPasswords::isCommonPassword($raw_password)) {
      throw new PhabricatorAuthPasswordException(
        pht(
          'The selected password is very weak: it is one of the most common '.
          'passwords in use. Choose a stronger password.'),
        pht('Very Weak'));
    }

    // If we're creating a brand new object (like registering a new user)
    // and it does not have a PHID yet, it isn't possible for it to have any
    // revoked passwords or colliding passwords either, so we can skip these
    // checks.

    $object = $this->getObject();

    if ($object->getPHID()) {
      if ($this->isRevokedPassword($password)) {
        throw new PhabricatorAuthPasswordException(
          pht(
            'The password you entered has been revoked. You can not reuse '.
            'a password which has been revoked. Choose a new password.'),
          pht('Revoked'));
      }

      if (!$this->isUniquePassword($password)) {
        throw new PhabricatorAuthPasswordException(
          pht(
            'The password you entered is the same as another password '.
            'associated with your account. Each password must be unique.'),
          pht('Not Unique'));
      }
    }

    // Prevent use of passwords which are similar to any object identifier.
    // For example, if your username is "alincoln", your password may not be
    // "alincoln", "lincoln", or "alincoln1".
    $viewer = $this->getViewer();
    $blocklist = $object->newPasswordBlocklist($viewer, $this);

    // Smallest number of overlapping characters that we'll consider to be
    // too similar.
    $minimum_similarity = 4;

    // Add the domain name to the blocklist.
    $base_uri = PhabricatorEnv::getAnyBaseURI();
    $base_uri = new PhutilURI($base_uri);
    $blocklist[] = $base_uri->getDomain();

    // Generate additional subterms by splitting the raw blocklist on
    // characters like "@", " " (space), and "." to break up email addresses,
    // readable names, and domain names into components.
    $terms_map = array();
    foreach ($blocklist as $term) {
      $terms_map[$term] = $term;
      foreach (preg_split('/[ @.]/', $term) as $subterm) {
        $terms_map[$subterm] = $term;
      }
    }

    // Skip very short terms: it's okay if your password has the substring
    // "com" in it somewhere even if the install is on "mycompany.com".
    foreach ($terms_map as $term => $source) {
      if (strlen($term) < $minimum_similarity) {
        unset($terms_map[$term]);
      }
    }

    // Normalize terms for comparison.
    $normal_map = array();
    foreach ($terms_map as $term => $source) {
      $term = phutil_utf8_strtolower($term);
      $normal_map[$term] = $source;
    }

    // Finally, make sure that none of the terms appear in the password,
    // and that the password does not appear in any of the terms.
    $normal_password = phutil_utf8_strtolower($raw_password);
    if (strlen($normal_password) >= $minimum_similarity) {
      foreach ($normal_map as $term => $source) {

        // See T2312. This may be required if the term list includes numeric
        // strings like "12345", which will be cast to integers when used as
        // array keys.
        $term = phutil_string_cast($term);

        if (strpos($term, $normal_password) === false &&
            strpos($normal_password, $term) === false) {
          continue;
        }

        throw new PhabricatorAuthPasswordException(
          pht(
            'The password you entered is very similar to a nonsecret account '.
            'identifier (like a username or email address). Choose a more '.
            'distinct password.'),
          pht('Not Distinct'));
      }
    }
  }

  public function isValidPassword(PhutilOpaqueEnvelope $envelope) {
    $this->requireSetup();

    $password_type = $this->getPasswordType();

    $passwords = $this->newQuery()
      ->withPasswordTypes(array($password_type))
      ->withIsRevoked(false)
      ->execute();

    $matches = $this->getMatches($envelope, $passwords);
    if (!$matches) {
      return false;
    }

    if ($this->shouldUpgradeHashers()) {
      $this->upgradeHashers($envelope, $matches);
    }

    return true;
  }

  public function isUniquePassword(PhutilOpaqueEnvelope $envelope) {
    $this->requireSetup();

    $password_type = $this->getPasswordType();

    // To test that the password is unique, we're loading all active and
    // revoked passwords for all roles for the given user, then throwing out
    // the active passwords for the current role (so a password can't
    // collide with itself).

    // Note that two different objects can have the same password (say,
    // users @alice and @bailey). We're only preventing @alice from using
    // the same password for everything.

    $passwords = $this->newQuery()
      ->execute();

    foreach ($passwords as $key => $password) {
      $same_type = ($password->getPasswordType() === $password_type);
      $is_active = !$password->getIsRevoked();

      if ($same_type && $is_active) {
        unset($passwords[$key]);
      }
    }

    $matches = $this->getMatches($envelope, $passwords);

    return !$matches;
  }

  public function isRevokedPassword(PhutilOpaqueEnvelope $envelope) {
    $this->requireSetup();

    // To test if a password is revoked, we're loading all revoked passwords
    // across all roles for the given user. If a password was revoked in one
    // role, you can't reuse it in a different role.

    $passwords = $this->newQuery()
      ->withIsRevoked(true)
      ->execute();

    $matches = $this->getMatches($envelope, $passwords);

    return (bool)$matches;
  }

  private function requireSetup() {
    if (!$this->getObject()) {
      throw new PhutilInvalidStateException('setObject');
    }

    if (!$this->getPasswordType()) {
      throw new PhutilInvalidStateException('setPasswordType');
    }

    if (!$this->getViewer()) {
      throw new PhutilInvalidStateException('setViewer');
    }

    if ($this->shouldUpgradeHashers()) {
      if (!$this->getContentSource()) {
        throw new PhutilInvalidStateException('setContentSource');
      }
    }
  }

  private function shouldUpgradeHashers() {
    if (!$this->getUpgradeHashers()) {
      return false;
    }

    if (PhabricatorEnv::isReadOnly()) {
      // Don't try to upgrade hashers if we're in read-only mode, since we
      // won't be able to write the new hash to the database.
      return false;
    }

    return true;
  }

  private function newQuery() {
    $viewer = $this->getViewer();
    $object = $this->getObject();
    $password_type = $this->getPasswordType();

    return id(new PhabricatorAuthPasswordQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()));
  }

  private function getMatches(
    PhutilOpaqueEnvelope $envelope,
    array $passwords) {

    $object = $this->getObject();

    $matches = array();
    foreach ($passwords as $password) {
      try {
        $is_match = $password->comparePassword($envelope, $object);
      } catch (PhabricatorPasswordHasherUnavailableException $ex) {
        $is_match = false;
      }

      if ($is_match) {
        $matches[] = $password;
      }
    }

    return $matches;
  }

  private function upgradeHashers(
    PhutilOpaqueEnvelope $envelope,
    array $passwords) {

    assert_instances_of($passwords, 'PhabricatorAuthPassword');

    $need_upgrade = array();
    foreach ($passwords as $password) {
      if (!$password->canUpgrade()) {
        continue;
      }
      $need_upgrade[] = $password;
    }

    if (!$need_upgrade) {
      return;
    }

    $upgrade_type = PhabricatorAuthPasswordUpgradeTransaction::TRANSACTIONTYPE;
    $viewer = $this->getViewer();
    $content_source = $this->getContentSource();

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    foreach ($need_upgrade as $password) {

      // This does the actual upgrade. We then apply a transaction to make
      // the upgrade more visible and auditable.
      $old_hasher = $password->getHasher();
      $password->upgradePasswordHasher($envelope, $this->getObject());
      $new_hasher = $password->getHasher();

      // NOTE: We must save the change before applying transactions because
      // the editor will reload the object to obtain a read lock.
      $password->save();

      $xactions = array();

      $xactions[] = $password->getApplicationTransactionTemplate()
        ->setTransactionType($upgrade_type)
        ->setNewValue($new_hasher->getHashName());

      $editor = $password->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSource($content_source)
        ->setOldHasher($old_hasher)
        ->applyTransactions($password, $xactions);
    }
    unset($unguarded);
  }

}
