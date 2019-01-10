<?php

interface PhabricatorAuthPasswordHashInterface {

  public function newPasswordDigest(
    PhutilOpaqueEnvelope $envelope,
    PhabricatorAuthPassword $password);

  /**
   * Return a list of strings which passwords associated with this object may
   * not be similar to.
   *
   * This method allows you to prevent users from selecting their username
   * as their password or picking other passwords which are trivially similar
   * to an account or object identifier.
   *
   * @param PhabricatorUser The user selecting the password.
   * @param PhabricatorAuthPasswordEngine The password engine updating a
   *  password.
   * @return list<string> Blocklist of nonsecret identifiers which the password
   *  should not be similar to.
   */
  public function newPasswordBlocklist(
    PhabricatorUser $viewer,
    PhabricatorAuthPasswordEngine $engine);

}
