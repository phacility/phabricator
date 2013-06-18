<?php

final class PhabricatorAuthValidateController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $failures = array();

    if (!strlen($request->getStr('phusr'))) {
      return $this->renderErrors(
        array(
          pht(
            'Login validation is missing expected parameter ("%s").',
            'phusr')));
    }

    $expect_phusr = $request->getStr('phusr');
    $actual_phusr = $request->getCookie('phusr');
    if ($actual_phusr != $expect_phusr) {
      if ($actual_phusr) {
        $failures[] = pht(
          "Attempted to set '%s' cookie to '%s', but your browser sent back ".
          "a cookie with the value '%s'. Clear your browser's cookies and ".
          "try again.",
          'phusr',
          $expect_phusr,
          $actual_phusr);
      } else {
        $failures[] = pht(
          "Attempted to set '%s' cookie to '%s', but your browser did not ".
          "accept the cookie. Check that cookies are enabled, clear them, ".
          "and try again.",
          'phusr',
          $expect_phusr);
      }
    }

    if (!$failures) {
      if (!$viewer->getPHID()) {
        $failures[] = pht(
          "Login cookie was set correctly, but your login session is not ".
          "valid. Try clearing cookies and logging in again.");
      }
    }

    if ($failures) {
      return $this->renderErrors($failures);
    }

    $next = $request->getCookie('next_uri');
    $request->clearCookie('next_uri');

    if (!PhabricatorEnv::isValidLocalWebResource($next)) {
      $next = '/';
    }

    return id(new AphrontRedirectResponse())->setURI($next);
  }

  private function renderErrors(array $messages) {
    return $this->renderErrorPage(
      pht('Login Failure'),
      $messages);
  }

}
