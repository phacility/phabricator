<?php

// This migration once upgraded VCS password hashing, but the table was
// later removed in 2018 (see T13043).

// Since almost four years have passed since this migration, the cost of
// losing this data is very small (users just need to reset their passwords),
// and a version of this migration against the modern schema isn't easy to
// implement or test, just skip the migration.

// This means that installs which upgrade from a version of Phabricator
// released prior to Feb 2014 to a version of Phabricator relased after
// Jan 2018 will need to have users reset VCS passwords.
