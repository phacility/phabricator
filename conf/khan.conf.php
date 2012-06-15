<?php

return array(

  // Uncomment this for initial setup, then recomment.
//setup  'phabricator.setup' => true,

  // This will be the base domain for your install, and must be configured.
  // Use "https://" if you have SSL. See below for some notes.
  'phabricator.base-uri' => 'http://phabricator.khanacademy.org/',

  // Only allow people at khanacademy.org to register.
  'auth.email-domains' => array(
    'khanacademy.org',
  ),		       

  // Allow, but don't require, a user to say how they did testing.
  'differential.require-test-plan-field' => false,

  // Allow logins via google.
  'google.auth-enabled' => true,
  'google.auth-permanent' => true,
  // Allow anyone with a google account to register.
  'google.registration-enabled' => true,

  // Logging.
  'log.access.path'             => '/home/ubuntu/logs/phabricator.log',

  // Basic email domain configuration.
  'metamta.default-address' => 'noreply@phabricator.khanacademy.org',
  'metamta.domain'          => 'phabricator.khanacademy.org',
  'metamta.can-send-as-user'    => true,
  // gmail threading will break if the subject changes.
  'metamta.vary-subjects' => false,

  // Connection information for MySQL.
  'mysql.host' => 'localhost',
  'mysql.user' => 'phabricator',
  'mysql.pass' => 'codereview',

  // Timezone for khan academy.
  'phabricator.timezone'    => 'America/Los_Angeles',
  'phabricator.csrf-key'    => '0016ee009c31da52bc9044dd5a989ff1ec6da80',

  // source-code highlighting is the bomb
  'pygments.enabled'            => true,

  // TODO(csilvers): enable recaptcha if brute-force attacks become a problem.

  // Docs say this is "pretty silly (but sort of awesome)". Good enough for me.
  'remarkup.enable-embedded-youtube' => true,

  // This apparently avoids some cookie-based attacks.
  'security.alternate-file-domain'  => 'http://phabricator-files.khanacademy.org/',

  // pygments doesn't know .q files are sql.  We add that.  (The
  // .arcconfig comes default.conf; I'm not sure if read_config does
  // merging on sub-arrays properly, so I just repeat it to be safe.)
  'syntax.filemap' => array(
    '@\.arcconfig$@' => 'js',
    '@\.q$@' => 'mysql',
  ),

) + phabricator_read_config_file('custom/khan-google.conf.php')
  + phabricator_read_config_file('production');
