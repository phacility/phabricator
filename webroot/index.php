<?php

$phabricator_root = dirname(dirname(__FILE__));
require_once $phabricator_root.'/support/PhabricatorStartup.php';

// If the preamble script exists, load it.
$preamble_path = $phabricator_root.'/support/preamble.php';
if (file_exists($preamble_path)) {
  require_once $preamble_path;
}

PhabricatorStartup::didStartup();

try {
  PhabricatorStartup::loadCoreLibraries();
  PhabricatorCaches::destroyRequestCache();

  $sink = new AphrontPHPHTTPSink();

  try {
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
