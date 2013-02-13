<?php

final class PhabricatorOAuthFailureView extends AphrontView {

  private $request;
  private $provider;
  private $exception;

  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function setOAuthProvider($provider) {
    $this->provider = $provider;
    return $this;
  }

  public function setException(Exception $e) {
    $this->exception = $e;
    return $this;
  }

  public function render() {
    $request = $this->request;
    $provider = $this->provider;
    $provider_name = $provider->getProviderName();

    $diagnose = null;

    $view = new AphrontRequestFailureView();
    $view->setHeader(pht('%s Auth Failed', $provider_name));
    if ($this->request) {
      $view->appendChild(
        hsprintf(
          '<p><strong>Description:</strong> %s</p>',
          $request->getStr('error_description')));
      $view->appendChild(
        hsprintf(
          '<p><strong>Error:</strong> %s</p>',
          $request->getStr('error')));
      $view->appendChild(
        hsprintf(
          '<p><strong>Error Reason:</strong> %s</p>',
          $request->getStr('error_reason')));
    } else if ($this->exception) {
      $view->appendChild(
        hsprintf(
          '<p><strong>Error Details:</strong> %s</p>',
          $this->exception->getMessage()));
    } else {
      // TODO: We can probably refine this.
      $view->appendChild(
        hsprintf(
          '<p>Unable to authenticate with %s. '.
          'There are several reasons this might happen:</p>'.
            '<ul>'.
              '<li>Phabricator may be configured with the wrong Application '.
              'Secret; or</li>'.
              '<li>the %s OAuth access token may have expired; or</li>'.
              '<li>%s may have revoked authorization for the Application; '.
              'or</li>'.
              '<li>%s may be having technical problems.</li>'.
            '</ul>'.
          '<p>You can try again, or login using another method.</p>',
          $provider_name,
          $provider_name,
          $provider_name,
          $provider_name));

      $provider_key = $provider->getProviderKey();
      $diagnose = hsprintf(
        '<a href="/oauth/%s/diagnose/" class="button green">'.
          'Diagnose %s OAuth Problems'.
        '</a>',
        $provider_key,
        $provider_name);
    }

    $view->appendChild(hsprintf(
      '<div class="aphront-failure-continue">'.
        '%s<a href="/login/" class="button">%s</a>'.
      '</div>',
      $diagnose,
      pht('Continue')));

    return $view->render();
  }

}
