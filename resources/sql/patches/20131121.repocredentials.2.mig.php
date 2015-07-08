<?php

// This migration originally imported repository credentials from the old
// inline format into Passphrase after the application was introduced. After
// about 18 months, following the introduction of Spaces, it stopped running
// cleanly. Installs older than Nov 2013 will need to manually fix repository
// credentials after updating. See T8746.
