<?php

interface PhabricatorAuthPasswordHashInterface {

  public function newPasswordDigest(
    PhutilOpaqueEnvelope $envelope,
    PhabricatorAuthPassword $password);

}
