<?php

return array(

  'phabricator.developer-mode'      => true,
  'darkconsole.enabled'             => true,
  'celerity.minify'                 => false,

) + phabricator_read_config_file('default');
