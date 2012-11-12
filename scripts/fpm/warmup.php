<?php

/**
 * NOTE: This is an ADVANCED feature that improves performance but adds a lot
 * of complexity! This is only suitable for production servers because workers
 * won't pick up changes between when they spawn and when they handle a request.
 *
 * Phabricator spends a significant portion of its runtime loading classes
 * and functions, even with APC enabled. Since we have very rigidly-defined
 * rules about what can go in a module (specifically: no side effects), it
 * is safe to load all the libraries *before* we receive a request.
 *
 * Normally, SAPIs don't provide a way to do this, but with a patched PHP-FPM
 * SAPI you can provide a warmup file that it will execute before a request
 * is received.
 *
 * We're limited in what we can do here, since request information won't
 * exist yet, but we can load class and function definitions, which is what
 * we're really interested in.
 *
 * Once this file exists, the FCGI process will drop into its normal accept loop
 * and eventually process a request.
 */
function __warmup__() {
  $root = dirname(dirname(dirname(dirname(__FILE__))));
  require_once $root.'/libphutil/src/__phutil_library_init__.php';
  require_once $root.'/arcanist/src/__phutil_library_init__.php';
  require_once $root.'/phabricator/src/__phutil_library_init__.php';

  // Load every symbol. We could possibly refine this -- we don't need to load
  // every Controller, for instance.
  $loader = new PhutilSymbolLoader();
  $loader->selectAndLoadSymbols();

  define('__WARMUP__', true);
}

__warmup__();
