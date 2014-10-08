<?php

abstract class PhabricatorOAuthServerDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'oauth_server';
  }

}
