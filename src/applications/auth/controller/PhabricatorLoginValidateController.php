<?php

final class PhabricatorLoginValidateController
  extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    $failures = array();

    if (!strlen($request->getStr('phusr'))) {
      throw new Exception(
        "Login validation is missing expected parameters!");
    }

    $expect_phusr = $request->getStr('phusr');
    $actual_phusr = $request->getCookie('phusr');
    if ($actual_phusr != $expect_phusr) {

      if ($actual_phusr) {
        $cookie_info = "sent back a cookie with the value '{$actual_phusr}'.";
      } else {
        $cookie_info = "did not accept the cookie.";
      }

      $failures[] =
        "Attempted to set 'phusr' cookie to '{$expect_phusr}', but your ".
        "browser {$cookie_info}";
    }

    if (!$failures) {
      if (!$request->getUser()->getPHID()) {
        $failures[] = "Cookies were set correctly, but your session ".
                      "isn't valid.";
      }
    }

    if ($failures) {

      $list = array();
      foreach ($failures as $failure) {
        $list[] = '<li>'.phutil_escape_html($failure).'</li>';
      }
      $list = '<ul>'.implode("\n", $list).'</ul>';

      $view = new AphrontRequestFailureView();
      $view->setHeader('Login Failed');
      $view->appendChild(
        '<p>Login failed:</p>'.
        $list.
        '<p><strong>Clear your cookies</strong> and try again.</p>');
      $view->appendChild(
        '<div class="aphront-failure-continue">'.
          '<a class="button" href="/login/">Try Again</a>'.
        '</div>');
      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => 'Login Failed',
        ));
    }

    $next = nonempty($request->getStr('next'), $request->getCookie('next_uri'));
    $request->clearCookie('next_uri');
    if (!PhabricatorEnv::isValidLocalWebResource($next)) {
      $next = '/';
    }

    return id(new AphrontRedirectResponse())->setURI($next);
  }

}
