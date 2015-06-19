<?php

interface DatabaseConfigurationProvider {

  public function __construct(
    LiskDAO $dao = null,
    $mode = 'r',
    $namespace = 'phabricator');

  public function getUser();
  public function getPassword();
  public function getHost();
  public function getPort();
  public function getDatabase();

}
