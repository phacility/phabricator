<?php

abstract class CelerityResourceController extends PhabricatorController {

  protected function buildResourceTransformer() {
    return null;
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldRequireEnabledUser() {
    return false;
  }

  public function shouldAllowPartialSessions() {
    return true;
  }

  abstract public function getCelerityResourceMap();

  protected function serveResource($path, $package_hash = null) {
    // Sanity checking to keep this from exposing anything sensitive, since it
    // ultimately boils down to disk reads.
    if (preg_match('@(//|\.\.)@', $path)) {
      return new Aphront400Response();
    }

    $type = CelerityResourceTransformer::getResourceType($path);
    $type_map = $this->getSupportedResourceTypes();

    if (empty($type_map[$type])) {
      throw new Exception("Only static resources may be served.");
    }

    if (AphrontRequest::getHTTPHeader('If-Modified-Since') &&
        !PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      // Return a "304 Not Modified". We don't care about the value of this
      // field since we never change what resource is served by a given URI.
      return $this->makeResponseCacheable(new Aphront304Response());
    }

    $map = $this->getCelerityResourceMap();

    if ($map->isPackageResource($path)) {
      $resource_names = $map->getResourceNamesForPackageName($path);
      if (!$resource_names) {
        return new Aphront404Response();
      }

      try {
        $data = array();
        foreach ($resource_names as $resource_name) {
          $data[] = $map->getResourceDataForName($resource_name);
        }
        $data = implode("\n\n", $data);
      } catch (Exception $ex) {
        return new Aphront404Response();
      }
    } else {
      try {
        $data = $map->getResourceDataForName($path);
      } catch (Exception $ex) {
        return new Aphront404Response();
      }
    }

    $xformer = $this->buildResourceTransformer();
    if ($xformer) {
      $data = $xformer->transformResource($path, $data);
    }

    $response = new AphrontFileResponse();
    $response->setContent($data);
    $response->setMimeType($type_map[$type]);

    // NOTE: This is a piece of magic required to make WOFF fonts work in
    // Firefox. Possibly we should generalize this.
    if ($type == 'woff') {
      // We could be more tailored here, but it's not currently trivial to
      // generate a comprehensive list of valid origins (an install may have
      // arbitrarily many Phame blogs, for example), and we lose nothing by
      // allowing access from anywhere.
      $response->addAllowOrigin("*");
    }

    return $this->makeResponseCacheable($response);
  }

  protected function getSupportedResourceTypes() {
    return array(
      'css' => 'text/css; charset=utf-8',
      'js'  => 'text/javascript; charset=utf-8',
      'png' => 'image/png',
      'gif' => 'image/gif',
      'jpg' => 'image/jpeg',
      'swf' => 'application/x-shockwave-flash',
      'woff' => 'font/woff',
      'eot' => 'font/eot',
      'ttf' => 'font/ttf',
    );
  }

  private function makeResponseCacheable(AphrontResponse $response) {
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    $response->setLastModified(time());

    return $response;
  }

}
