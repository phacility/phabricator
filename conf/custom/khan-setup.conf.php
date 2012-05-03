<?php

return array(

  // Important! This will put Phabricator into setup mode to help you
  // configure things.
  'phabricator.setup' => true,

) + phabricator_read_config_file('custom/khan');
