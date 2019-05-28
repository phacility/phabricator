<?php

// See T11741. Long ago, in T11922, we switched from "MyISAM FULLTEXT" to
// "InnoDB FULLTEXT". This migration prompted installs to rebuild the index.

// Later, in T12974, we switched from "InnoDB FULLTEXT" to "Ferret", mostly
// mooting this. The underlying tables and engines were later removed entirely.
