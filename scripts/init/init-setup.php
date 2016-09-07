<?php

// Initialize a setup script which may run before database connections have
// been configured. Scripts initialized in this way ignore database errors
// while building database configuration and continue.

require_once dirname(__FILE__).'/lib.php';

init_phabricator_script(
  array(
    'config.optional' => true,
  ));
