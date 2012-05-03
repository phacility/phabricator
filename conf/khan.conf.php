<?php

return array(

  // This will be the base domain for your install, and must be configured.
  // Use "https://" if you have SSL. See below for some notes.
  'phabricator.base-uri' => 'http://phabricator.khanacademy.org/',

  // Connection information for MySQL.
  'mysql.host' => 'localhost',
  'mysql.user' => 'phabricator',
  'mysql.pass' => 'codereview',

  // Basic email domain configuration.
  'metamta.default-address' => 'noreply@phabricator.khanacademy.org',
  'metamta.domain'          => 'phabricator.khanacademy.org',

  // NOTE: Check default.conf.php for detailed explanations of all the
  // configuration options, including these.

) + phabricator_read_config_file('production');
