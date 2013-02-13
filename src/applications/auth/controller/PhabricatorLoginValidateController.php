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
        $list[] = phutil_tag('li', array(), $failure);
      }
      $list = phutil_tag('ul', array(), $list);

      $view = new AphrontRequestFailureView();
      $view->setHeader(pht('Login Failed'));
      $view->appendChild(hsprintf(
        '<p>%s</p>%s<p>%s</p>',
        pht('Login failed:'),
        $list,
        pht(
          '<strong>Clear your cookies</strong> and try again.',
          hsprintf(''))));
      $view->appendChild(hsprintf(
        '<div class="aphront-failure-continue">'.
          '<a class="button" href="/login/">%s</a>'.
        '</div>',
        pht('Try Again')));
      return $this->buildStandardPageResponse(
        $view,
        array(
          'title' => pht('Login Failed'),
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
