<?php

phabricator_startup();

$fatal_exception = null;
try {
  PhabricatorStartup::beginStartupPhase('libraries');
  PhabricatorStartup::loadCoreLibraries();

  PhabricatorStartup::beginStartupPhase('purge');
  PhabricatorCaches::destroyRequestCache();

  PhabricatorStartup::beginStartupPhase('sink');
  $sink = new AphrontPHPHTTPSink();

  // PHP introduced a "Throwable" interface in PHP 7 and began making more
  // runtime errors throw as "Throwable" errors. This is generally good, but
  // makes top-level exception handling that is compatible with both PHP 5
  // and PHP 7 a bit tricky.

  // In PHP 5, "Throwable" does not exist, so "catch (Throwable $ex)" catches
  // nothing.

  // In PHP 7, various runtime conditions raise an Error which is a Throwable
  // but NOT an Exception, so "catch (Exception $ex)" will not catch them.

  // To cover both cases, we "catch (Exception $ex)" to catch everything in
  // PHP 5, and most things in PHP 7. Then, we "catch (Throwable $ex)" to catch
  // everything else in PHP 7. For the most part, we only need to do this at
  // the top level.

  $main_exception = null;
  try {
    PhabricatorStartup::beginStartupPhase('run');
    AphrontApplicationConfiguration::runHTTPRequest($sink);
  } catch (Exception $ex) {
    $main_exception = $ex;
  } catch (Throwable $ex) {
    $main_exception = $ex;
  }

  if ($main_exception) {
    $response_exception = null;
    try {
      $response = new AphrontUnhandledExceptionResponse();
      $response->setException($main_exception);
      $response->setShowStackTraces($sink->getShowStackTraces());

      PhabricatorStartup::endOutputCapture();
      $sink->writeResponse($response);
    } catch (Exception $ex) {
      $response_exception = $ex;
    } catch (Throwable $ex) {
      $response_exception = $ex;
    }

    // If we hit a rendering exception, ignore it and throw the original
    // exception. It is generally more interesting and more likely to be
    // the root cause.

    if ($response_exception) {
      throw $main_exception;
    }
  }
} catch (Exception $ex) {
  $fatal_exception = $ex;
} catch (Throwable $ex) {
  $fatal_exception = $ex;
}

if ($fatal_exception) {
  PhabricatorStartup::didEncounterFatalException(
    'Core Exception',
    $fatal_exception,
    false);
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
  require_once $root.'/support/startup/preamble-utils.php';

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
