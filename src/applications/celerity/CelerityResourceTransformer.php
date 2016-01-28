<?php

final class CelerityResourceTransformer extends Phobject {

  private $minify;
  private $rawURIMap;
  private $celerityMap;
  private $translateURICallback;
  private $currentPath;
  private $postprocessorKey;
  private $variableMap;

  public function setPostprocessorKey($postprocessor_key) {
    $this->postprocessorKey = $postprocessor_key;
    return $this;
  }

  public function getPostprocessorKey() {
    return $this->postprocessorKey;
  }

  public function setTranslateURICallback($translate_uricallback) {
    $this->translateURICallback = $translate_uricallback;
    return $this;
  }

  public function setMinify($minify) {
    $this->minify = $minify;
    return $this;
  }

  public function setCelerityMap(CelerityResourceMap $celerity_map) {
    $this->celerityMap = $celerity_map;
    return $this;
  }

  public function setRawURIMap(array $raw_urimap) {
    $this->rawURIMap = $raw_urimap;
    return $this;
  }

  public function getRawURIMap() {
    return $this->rawURIMap;
  }

  /**
   * @phutil-external-symbol function jsShrink
   */
  public function transformResource($path, $data) {
    $type = self::getResourceType($path);

    switch ($type) {
      case 'css':
        $data = $this->replaceCSSPrintRules($path, $data);
        $data = $this->replaceCSSVariables($path, $data);
        $data = preg_replace_callback(
          '@url\s*\((\s*[\'"]?.*?)\)@s',
          nonempty(
            $this->translateURICallback,
            array($this, 'translateResourceURI')),
          $data);
        break;
    }

    if (!$this->minify) {
      return $data;
    }

    // Some resources won't survive minification (like d3.min.js), and are
    // marked so as not to be minified.
    if (strpos($data, '@'.'do-not-minify') !== false) {
      return $data;
    }

    switch ($type) {
      case 'css':
        // Remove comments.
        $data = preg_replace('@/\*.*?\*/@s', '', $data);
        // Remove whitespace around symbols.
        $data = preg_replace('@\s*([{}:;,])\s*@', '\1', $data);
        // Remove unnecessary semicolons.
        $data = preg_replace('@;}@', '}', $data);
        // Replace #rrggbb with #rgb when possible.
        $data = preg_replace(
          '@#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3@i',
          '#\1\2\3',
          $data);
        $data = trim($data);
        break;
      case 'js':

        // If `jsxmin` is available, use it. jsxmin is the Javelin minifier and
        // produces the smallest output, but is complicated to build.
        if (Filesystem::binaryExists('jsxmin')) {
          $future = new ExecFuture('jsxmin __DEV__:0');
          $future->write($data);
          list($err, $result) = $future->resolve();
          if (!$err) {
            $data = $result;
            break;
          }
        }

        // If `jsxmin` is not available, use `JsShrink`, which doesn't compress
        // quite as well but is always available.
        $root = dirname(phutil_get_library_root('phabricator'));
        require_once $root.'/externals/JsShrink/jsShrink.php';
        $data = jsShrink($data);

        break;
    }

    return $data;
  }

  public static function getResourceType($path) {
    return last(explode('.', $path));
  }

  public function translateResourceURI(array $matches) {
    $uri = trim($matches[1], "'\" \r\t\n");
    $tail = '';

    // If the resource URI has a query string or anchor, strip it off before
    // we go looking for the resource. We'll stitch it back on later. This
    // primarily affects FontAwesome.

    $parts = preg_split('/(?=[?#])/', $uri, 2);
    if (count($parts) == 2) {
      $uri = $parts[0];
      $tail = $parts[1];
    }

    $alternatives = array_unique(
      array(
        $uri,
        ltrim($uri, '/'),
      ));

    foreach ($alternatives as $alternative) {
      if ($this->rawURIMap !== null) {
        if (isset($this->rawURIMap[$alternative])) {
          $uri = $this->rawURIMap[$alternative];
          break;
        }
      }

      if ($this->celerityMap) {
        $resource_uri = $this->celerityMap->getURIForName($alternative);
        if ($resource_uri) {
          // Check if we can use a data URI for this resource. If not, just
          // use a normal Celerity URI.
          $data_uri = $this->generateDataURI($alternative);
          if ($data_uri) {
            $uri = $data_uri;
          } else {
            $uri = $resource_uri;
          }
          break;
        }
      }
    }

    return 'url('.$uri.$tail.')';
  }

  private function replaceCSSVariables($path, $data) {
    $this->currentPath = $path;
    return preg_replace_callback(
      '/{\$([^}]+)}/',
      array($this, 'replaceCSSVariable'),
      $data);
  }

  private function replaceCSSPrintRules($path, $data) {
    $this->currentPath = $path;
    return preg_replace_callback(
      '/!print\s+(.+?{.+?})/s',
      array($this, 'replaceCSSPrintRule'),
      $data);
  }

  public function getCSSVariableMap() {
    $postprocessor_key = $this->getPostprocessorKey();
    $postprocessor = CelerityPostprocessor::getPostprocessor(
      $postprocessor_key);

    if (!$postprocessor) {
      $postprocessor = CelerityPostprocessor::getPostprocessor(
        CelerityDefaultPostprocessor::POSTPROCESSOR_KEY);
    }

    return $postprocessor->getVariables();
  }

  public function replaceCSSVariable($matches) {
    if (!$this->variableMap) {
      $this->variableMap = $this->getCSSVariableMap();
    }

    $var_name = $matches[1];
    if (empty($this->variableMap[$var_name])) {
      $path = $this->currentPath;
      throw new Exception(
        pht(
          "CSS file '%s' has unknown variable '%s'.",
          $path,
          $var_name));
    }

    return $this->variableMap[$var_name];
  }

  public function replaceCSSPrintRule($matches) {
    $rule = $matches[1];

    $rules = array();
    $rules[] = '.printable '.$rule;
    $rules[] = "@media print {\n  ".str_replace("\n", "\n  ", $rule)."\n}\n";

    return implode("\n\n", $rules);
  }


  /**
   * Attempt to generate a data URI for a resource. We'll generate a data URI
   * if the resource is a valid resource of an appropriate type, and is
   * small enough. Otherwise, this method will return `null` and we'll end up
   * using a normal URI instead.
   *
   * @param string  Resource name to attempt to generate a data URI for.
   * @return string|null Data URI, or null if we declined to generate one.
   */
  private function generateDataURI($resource_name) {
    $ext = last(explode('.', $resource_name));
    switch ($ext) {
      case 'png':
        $type = 'image/png';
        break;
      case 'gif':
        $type = 'image/gif';
        break;
      case 'jpg':
        $type = 'image/jpeg';
        break;
      default:
        return null;
    }

    // In IE8, 32KB is the maximum supported URI length.
    $maximum_data_size = (1024 * 32);

    $data = $this->celerityMap->getResourceDataForName($resource_name);
    if (strlen($data) >= $maximum_data_size) {
      // If the data is already too large on its own, just bail before
      // encoding it.
      return null;
    }

    $uri = 'data:'.$type.';base64,'.base64_encode($data);
    if (strlen($uri) >= $maximum_data_size) {
      return null;
    }

    return $uri;
  }

}
