<?php

return array(

  'darkconsole.enabled'             => true,
  'celerity.force-disk-reads'       => true,
  'phabricator.show-stack-traces'   => true,
  'phabricator.show-error-callout'  => true,
  'celerity.minify'                 => false,

) + phabricator_read_config_file('default');
