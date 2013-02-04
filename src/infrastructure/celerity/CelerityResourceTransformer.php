<?php

/**
 * @group celerity
 */
final class CelerityResourceTransformer {

  private $minify;
  private $rawResourceMap;
  private $celerityMap;
  private $translateURICallback;

  public function setTranslateURICallback($translate_uricallback) {
    $this->translateURICallback = $translate_uricallback;
    return $this;
  }

  public function setMinify($minify) {
    $this->minify = $minify;
    return $this;
  }

  public function setRawResourceMap(array $raw_resource_map) {
    $this->rawResourceMap = $raw_resource_map;
    return $this;
  }

  public function setCelerityMap(CelerityResourceMap $celerity_map) {
    $this->celerityMap = $celerity_map;
    return $this;
  }

  public function transformResource($path, $data) {
    $type = self::getResourceType($path);

    switch ($type) {
      case 'css':
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

    // Some resources won't survive minification (like Raphael.js), and are
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
        $root = dirname(phutil_get_library_root('phabricator'));
        $bin = $root.'/externals/javelin/support/jsxmin/jsxmin';

        if (@file_exists($bin)) {
          $future = new ExecFuture('%s __DEV__:0', $bin);
          $future->write($data);
          list($err, $result) = $future->resolve();
          if (!$err) {
            $data = $result;
          }
        }
        break;
    }

    return $data;
  }

  public static function getResourceType($path) {
    return last(explode('.', $path));
  }

  public function translateResourceURI(array $matches) {
    $uri = trim($matches[1], "'\" \r\t\n");

    if ($this->rawResourceMap) {
      if (isset($this->rawResourceMap[$uri]['uri'])) {
        $uri = $this->rawResourceMap[$uri]['uri'];
      }
    } else if ($this->celerityMap) {
      $info = $this->celerityMap->lookupFileInformation($uri);
      if ($info) {
        $uri = $info['uri'];
      }
    }

    return 'url('.$uri.')';
  }

}
