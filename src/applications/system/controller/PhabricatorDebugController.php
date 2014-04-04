<?php

/**
 * This controller eases debugging of application problems that don't repro
 * locally by allowing installs to add arbitrary debugging code easily. To use
 * it:
 *
 *  - Write some diagnostic script.
 *  - Instruct the user to install it in `/support/debug.php`.
 *  - Tell them to visit `/debug/`.
 */
final class PhabricatorDebugController extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    if (!Filesystem::pathExists($this->getDebugFilePath())) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    ob_start();
    require_once $this->getDebugFilePath();
    $out = ob_get_clean();

    $response = new AphrontWebpageResponse();
    $response->setContent(phutil_tag('pre', array(), $out));
    return $response;
  }

  private function getDebugFilePath() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/support/debug.php';
  }
}
