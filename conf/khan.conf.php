<?php

return array(

  // This will be the base domain for your install, and must be configured.
  // Use "https://" if you have SSL. See below for some notes.
  'phabricator.base-uri' => 'http://phabricator.khanacademy.org/',

  // We trust our code-authors to not close an audit unless there's
  // a good reason.
  'audit.can-author-close-audit' => true,

  // Only allow people at khanacademy.org to register.
  'auth.email-domains' => array(
    'khanacademy.org',
  ),

  // Allow, but don't require, a user to say how they did testing.
  'differential.require-test-plan-field' => false,
  'differential.allow-reopen' => true,

  // Logging.
  'log.access.path'             => '/home/ubuntu/logs/phabricator.log',

  // Custom Maniphest fields
  'maniphest.custom-field-definitions' => array(
    'khan:duedate' => array(
      'name'       => 'Target Completion Date',
      'type'       => 'date',
      'caption'    => 'The date you expect to have this task completed by',
      'required'   => false,
    )
  ),

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

  // pygments doesn't know .q files are sql or that jsx is javascript(-ish).
  // We add that.  (The .arcconfig comes default.conf; I'm not sure if
  // read_config does merging on sub-arrays properly, so I just repeat it to be
  // safe.)
  'syntax.filemap' => array(
    '@\.arcconfig$@' => 'js',
    '@\.q$@' => 'mysql',
    '@\.jsx$@' => 'js',
  ),

) + phabricator_read_config_file('production');
