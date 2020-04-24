<?php

// For a while in November 2015, attachment edges between pastes and their
// underlying file data were not written correctly. This restores edges for
// any missing pastes.

// See T13510. The "pastebin" database was later renamed to "paste", which
// broke this migration. The migration was removed in 2020 since it seems
// plausible that zero installs are impacted (only installs that ran code
// from November 2015 and have not upgraded in five years could possibly be
// impacted).
