<?php

phabricator_startup();

try {
  PhabricatorStartup::beginStartupPhase('libraries');
  PhabricatorStartup::loadCoreLibraries();

  PhabricatorStartup::beginStartupPhase('purge');
  PhabricatorCaches::destroyRequestCache();

  PhabricatorStartup::beginStartupPhase('sink');
  $sink = new AphrontPHPHTTPSink();

  try {
    PhabricatorStartup::beginStartupPhase('run');
    AphrontApplicationConfiguration::runHTTPRequest($sink);
  } catch (Exception $ex) {
    try {
      $response = new AphrontUnhandledExceptionResponse();
      $response->setException($ex);

      PhabricatorStartup::endOutputCapture();
      $sink->writeResponse($response);
    } catch (Exception $response_exception) {
      // If we hit a rendering exception, ignore it and throw the original
      // exception. It is generally more interesting and more likely to be
      // the root cause.
      throw $ex;
    }
  }
} catch (Exception $ex) {
  PhabricatorStartup::didEncounterFatalException('Core Exception', $ex, false);
}

function phabricator_startup() {
  // Load the PhabricatorStartup class itself.
  $t_startup = microtime(true);
  $root = dirname(dirname(__FILE__));
  require_once $root.'/support/startup/PhabricatorStartup.php';

  // Load client limit classes so the preamble can configure limits.
  require_once $root.'/support/startup/PhabricatorClientLimit.php';
  require_once $root.'/support/startup/PhabricatorClientRateLimit.php';
  require_once $root.'/support/startup/PhabricatorClientConnectionLimit.php';

  // If the preamble script exists, load it.
  $t_preamble = microtime(true);
  $preamble_path = $root.'/support/preamble.php';
  if (file_exists($preamble_path)) {
    require_once $preamble_path;
  }

  $t_hook = microtime(true);
  PhabricatorStartup::didStartup($t_startup);

  PhabricatorStartup::recordStartupPhase('startup.init', $t_startup);
  PhabricatorStartup::recordStartupPhase('preamble', $t_preamble);
  PhabricatorStartup::recordStartupPhase('hook', $t_hook);
}
