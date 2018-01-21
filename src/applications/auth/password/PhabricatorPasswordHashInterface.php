<?php

interface PhabricatorPasswordHashInterface {

  public function newPasswordDigest(
    PhutilOpaqueEnvelope $envelope,
    PhabricatorAuthPassword $password);

}
