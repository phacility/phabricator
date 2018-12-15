<?php

// See T13225. Long ago, this upgraded session key storage from unhashed to
// HMAC-SHA1 here. We later upgraded storage to HMAC-SHA256, so this is initial
// upgrade is now fairly pointless. Dropping this migration entirely only logs
// users out of installs that waited more than 5 years to upgrade, which seems
// like a reasonable behavior.
