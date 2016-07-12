<?php

interface PhabricatorSSHPublicKeyInterface {

  /**
   * Provide a URI for SSH key workflows to return to after completing.
   *
   * When an actor adds, edits or deletes a public key, they'll be returned to
   * this URI. For example, editing user keys returns the actor to the settings
   * panel. Editing device keys returns the actor to the device page.
   */
  public function getSSHPublicKeyManagementURI(PhabricatorUser $viewer);


  /**
   * Provide a default name for generated SSH keys.
   */
  public function getSSHKeyDefaultName();

  public function getSSHKeyNotifyPHIDs();

}
