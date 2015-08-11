<?php

final class PhabricatorAuthValidateController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldAllowPartialSessions() {
    return true;
  }

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $failures = array();

    if (!strlen($request->getStr('expect'))) {
      return $this->renderErrors(
        array(
          pht(
            'Login validation is missing expected parameter ("%s").',
            'phusr'),
        ));
    }

    $expect_phusr = $request->getStr('expect');
    $actual_phusr = $request->getCookie(PhabricatorCookies::COOKIE_USERNAME);
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
          'Login cookie was set correctly, but your login session is not '.
          'valid. Try clearing cookies and logging in again.');
      }
    }

    if ($failures) {
      return $this->renderErrors($failures);
    }

    $finish_uri = $this->getApplicationURI('finish/');
    return id(new AphrontRedirectResponse())->setURI($finish_uri);
  }

  private function renderErrors(array $messages) {
    return $this->renderErrorPage(
      pht('Login Failure'),
      $messages);
  }

}
