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

  public function shouldAllowLegallyNonCompliantUsers() {
    return true;
  }

  abstract public function getCelerityResourceMap();

  protected function serveResource(array $spec) {
    $path = $spec['path'];
    $hash = idx($spec, 'hash');

    // Sanity checking to keep this from exposing anything sensitive, since it
    // ultimately boils down to disk reads.
    if (preg_match('@(//|\.\.)@', $path)) {
      return new Aphront400Response();
    }

    $type = CelerityResourceTransformer::getResourceType($path);
    $type_map = self::getSupportedResourceTypes();

    if (empty($type_map[$type])) {
      throw new Exception(pht('Only static resources may be served.'));
    }

    $dev_mode = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');

    $map = $this->getCelerityResourceMap();
    $expect_hash = $map->getHashForName($path);

    // Test if the URI hash is correct for our current resource map. If it
    // is not, refuse to cache this resource. This avoids poisoning caches
    // and CDNs if we're getting a request for a new resource to an old node
    // shortly after a push.
    $is_cacheable = ($hash === $expect_hash);
    $is_locally_cacheable = $this->isLocallyCacheableResourceType($type);
    if (AphrontRequest::getHTTPHeader('If-Modified-Since') && $is_cacheable) {
      // Return a "304 Not Modified". We don't care about the value of this
      // field since we never change what resource is served by a given URI.
      return $this->makeResponseCacheable(new Aphront304Response());
    }

    $cache = null;
    $data = null;
    if ($is_cacheable && $is_locally_cacheable && !$dev_mode) {
      $cache = PhabricatorCaches::getImmutableCache();

      $request_path = $this->getRequest()->getPath();
      $cache_key = $this->getCacheKey($request_path);

      $data = $cache->getKey($cache_key);
    }

    if ($data === null) {
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

      if ($cache) {
        $cache->setKey($cache_key, $data);
      }
    }

    $response = new AphrontFileResponse();
    $response->setContent($data);
    $response->setMimeType($type_map[$type]);

    // NOTE: This is a piece of magic required to make WOFF fonts work in
    // Firefox and IE. Possibly we should generalize this more.

    $cross_origin_types = array(
      'woff' => true,
      'woff2' => true,
      'eot' => true,
    );

    if (isset($cross_origin_types[$type])) {
      // We could be more tailored here, but it's not currently trivial to
      // generate a comprehensive list of valid origins (an install may have
      // arbitrarily many Phame blogs, for example), and we lose nothing by
      // allowing access from anywhere.
      $response->addAllowOrigin('*');
    }

    if ($is_cacheable) {
      $response = $this->makeResponseCacheable($response);
    }

    return $response;
  }

  public static function getSupportedResourceTypes() {
    return array(
      'css' => 'text/css; charset=utf-8',
      'js'  => 'text/javascript; charset=utf-8',
      'png' => 'image/png',
      'svg' => 'image/svg+xml',
      'gif' => 'image/gif',
      'jpg' => 'image/jpeg',
      'swf' => 'application/x-shockwave-flash',
      'woff' => 'font/woff',
      'woff2' => 'font/woff2',
      'eot' => 'font/eot',
      'ttf' => 'font/ttf',
      'mp3' => 'audio/mpeg',
    );
  }

  private function makeResponseCacheable(AphrontResponse $response) {
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    $response->setLastModified(time());
    $response->setCanCDN(true);

    return $response;
  }


  /**
   * Is it appropriate to cache the data for this resource type in the fast
   * immutable cache?
   *
   * Generally, text resources (which are small, and expensive to process)
   * are cached, while other types of resources (which are large, and cheap
   * to process) are not.
   *
   * @param string  Resource type.
   * @return bool   True to enable caching.
   */
  private function isLocallyCacheableResourceType($type) {
    $types = array(
      'js' => true,
      'css' => true,
    );

    return isset($types[$type]);
  }

  protected function getCacheKey($path) {
    return 'celerity:'.PhabricatorHash::digestToLength($path, 64);
  }

}
