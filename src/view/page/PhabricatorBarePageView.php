<?php

/**
 * This is a bare HTML page view which has access to Phabricator page
 * infrastructure like Celerity, but no content or builtin static resources.
 * You basically get a valid HMTL5 document and an empty body tag.
 *
 * @concrete-extensible
 */
class PhabricatorBarePageView extends AphrontPageView {

  private $request;
  private $controller;
  private $frameable;
  private $deviceReady;

  private $bodyContent;

  public function setController(AphrontController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function setFrameable($frameable) {
    $this->frameable = $frameable;
    return $this;
  }

  public function getFrameable() {
    return $this->frameable;
  }

  public function setDeviceReady($device_ready) {
    $this->deviceReady = $device_ready;
    return $this;
  }

  public function getDeviceReady() {
    return $this->deviceReady;
  }

  protected function willRenderPage() {
    // We render this now to resolve static resources so they can appear in the
    // document head.
    $this->bodyContent = $this->renderChildren();
  }

  protected function getHead() {
    $framebust = null;
    if (!$this->getFrameable()) {
      $framebust = '(top != self) && top.location.replace(self.location.href);';
    }

    $viewport_tag = null;
    if (PhabricatorEnv::getEnvConfig('preview.viewport-meta-tag') ||
        $this->getDeviceReady()) {
      $viewport_tag = phutil_render_tag(
        'meta',
        array(
          'name' => 'viewport',
          'content' => 'width=device-width, '.
                       'initial-scale=1, '.
                       'maximum-scale=1',
        ));
    }

    $response = CelerityAPI::getStaticResourceResponse();

    $head = array(
      $viewport_tag,

      '<script type="text/javascript">'.
        $framebust.
        'window.__DEV__=1;'.
      '</script>',

      $response->renderResourcesOfType('css'),
    );

    return implode("\n", $head);
  }

  protected function getBody() {
    return $this->bodyContent;
  }

  protected function getTail() {
    $response = CelerityAPI::getStaticResourceResponse();
    return $response->renderResourcesOfType('js');
  }

}
