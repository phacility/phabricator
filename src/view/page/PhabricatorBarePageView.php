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
    $this->bodyContent = phutil_implode_html('', $this->renderChildren());
  }

  protected function getHead() {
    $viewport_tag = null;
    if ($this->getDeviceReady()) {
      $viewport_tag = phutil_tag(
        'meta',
        array(
          'name' => 'viewport',
          'content' => 'width=device-width, '.
                       'initial-scale=1, '.
                       'user-scalable=no',
        ));
    }

    $referrer_tag = phutil_tag(
      'meta',
      array(
        'name' => 'referrer',
        'content' => 'no-referrer',
      ));


    $mask_icon = phutil_tag(
      'link',
      array(
        'rel' => 'mask-icon',
        'color' => '#3D4B67',
        'href' => celerity_get_resource_uri(
          '/rsrc/favicons/mask-icon.svg'),
      ));

    $favicon_links = $this->newFavicons();

    $response = CelerityAPI::getStaticResourceResponse();

    if ($this->getRequest()) {
      $viewer = $this->getRequest()->getViewer();
      if ($viewer) {
        $postprocessor_key = $viewer->getUserSetting(
          PhabricatorAccessibilitySetting::SETTINGKEY);
        if (strlen($postprocessor_key)) {
          $response->setPostProcessorKey($postprocessor_key);
        }
      }
    }

    return hsprintf(
      '%s%s%s%s%s',
      $viewport_tag,
      $mask_icon,
      $favicon_links,
      $referrer_tag,
      $response->renderResourcesOfType('css'));
  }

  protected function getBody() {
    return $this->bodyContent;
  }

  protected function getTail() {
    $response = CelerityAPI::getStaticResourceResponse();
    return $response->renderResourcesOfType('js');
  }

  private function newFavicons() {
    $favicon_refs = array(
      array(
        'rel' => 'apple-touch-icon',
        'sizes' => '76x76',
        'width' => 76,
        'height' => 76,
      ),
      array(
        'rel' => 'apple-touch-icon',
        'sizes' => '120x120',
        'width' => 120,
        'height' => 120,
      ),
      array(
        'rel' => 'apple-touch-icon',
        'sizes' => '152x152',
        'width' => 152,
        'height' => 152,
      ),
      array(
        'rel' => 'icon',
        'id' => 'favicon',
        'width' => 64,
        'height' => 64,
      ),
    );

    $fetch_refs = array();
    foreach ($favicon_refs as $key => $spec) {
      $ref = id(new PhabricatorFaviconRef())
        ->setWidth($spec['width'])
        ->setHeight($spec['height']);

      $favicon_refs[$key]['ref'] = $ref;
      $fetch_refs[] = $ref;
    }

    id(new PhabricatorFaviconRefQuery())
      ->withRefs($fetch_refs)
      ->execute();

    $favicon_links = array();
    foreach ($favicon_refs as $spec) {
      $favicon_links[] = phutil_tag(
        'link',
        array(
          'rel' => $spec['rel'],
          'sizes' => idx($spec, 'sizes'),
          'id' => idx($spec, 'id'),
          'href' => $spec['ref']->getURI(),
        ));
    }

    return $favicon_links;
  }

}
