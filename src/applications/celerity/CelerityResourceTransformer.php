<?php

final class CelerityResourceTransformer {

  private $minify;
  private $rawURIMap;
  private $celerityMap;
  private $translateURICallback;
  private $currentPath;

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

  public static function getCSSVariableMap() {
    return array(
      // Fonts
      'basefont' => "13px/1.231 'Segoe UI', 'Segoe UI Web Regular', ".
        "'Segoe UI Symbol', 'Helvetica Neue', Helvetica, Arial, sans-serif",

      // Drop Shadow
      'dropshadow' => '0 1px 6px rgba(0, 0, 0, .25)',

      // Base Colors
      'red'           => '#c0392b',
      'lightred'      => '#f4dddb',
      'orange'        => '#e67e22',
      'lightorange'   => '#f7e2d4',
      'yellow'        => '#f1c40f',
      'lightyellow'   => '#fdf5d4',
      'green'         => '#139543',
      'lightgreen'    => '#d7eddf',
      'blue'          => '#2980b9',
      'lightblue'     => '#daeaf3',
      'sky'           => '#3498db',
      'lightsky'      => '#ddeef9',
      'indigo'        => '#6e5cb6',
      'lightindigo'   => '#eae6f7',
      'pink'          => '#da49be',
      'lightpink'     => '#fbeaf8',
      'violet'        => '#8e44ad',
      'lightviolet'   => '#ecdff1',
      'charcoal'      => '#4b4d51',
      'backdrop'      => '#dadee7',
      'hovergrey'     => '#c5cbcf',
      'hoverblue'     => '#eceff5',
      'hoverborder'   => '#dfe1e9',
      'hoverselectedgrey' => '#bbc4ca',
      'hoverselectedblue' => '#e6e9ee',

      // Base Greys
      'lightgreyborder'     => '#C7CCD9',
      'greyborder'          => '#A1A6B0',
      'darkgreyborder'      => '#676A70',
      'lightgreytext'       => '#92969D',
      'greytext'            => '#74777D',
      'darkgreytext'        => '#4B4D51',
      'lightgreybackground' => '#F7F7F7',
      'greybackground'      => '#EBECEE',
      'darkgreybackground'  => '#DFE0E2',

      // Base Blues
      'thinblueborder'      => '#DDE8EF',
      'lightblueborder'     => '#BFCFDA',
      'blueborder'          => '#8C98B8',
      'darkblueborder'      => '#626E82',
      'lightbluebackground' => '#F8F9FC',
      'bluebackground'      => '#DAE7FF',
      'lightbluetext'       => '#8C98B8',
      'bluetext'            => '#6B748C',
      'darkbluetext'        => '#464C5C',

      // Base Greens
      'lightgreenborder'      => '#bfdac1',
      'greenborder'           => '#8cb89c',
      'greentext'             => '#3e6d35',
      'lightgreenbackground'  => '#e6f2e4',

      // Base Red
      'lightredborder'        => '#f4c6c6',
      'redborder'             => '#eb9797',
      'redtext'               => '#802b2b',
      'lightredbackground'    => '#f5e1e1',

      // Base Violet
      'lightvioletborder'     => '#cfbddb',
      'violetborder'          => '#b589ba',
      'violettext'            => '#603c73',
      'lightvioletbackground' => '#e9dfee',

      // Shades are a more muted set of our base colors
      // better suited to blending into other UIs.

      // Shade Red
      'sh-lightredborder'     => '#efcfcf',
      'sh-redborder'          => '#d1abab',
      'sh-redicon'            => '#c85a5a',
      'sh-redtext'            => '#a53737',
      'sh-redbackground'      => '#f7e6e6',

      // Shade Orange
      'sh-lightorangeborder'  => '#f8dcc3',
      'sh-orangeborder'       => '#dbb99e',
      'sh-orangeicon'         => '#e78331',
      'sh-orangetext'         => '#ba6016',
      'sh-orangebackground'   => '#fbede1',

      // Shade Yellow
      'sh-lightyellowborder'  => '#e9dbcd',
      'sh-yellowborder'       => '#c9b8a8',
      'sh-yellowicon'         => '#9b946e',
      'sh-yellowtext'         => '#726f56',
      'sh-yellowbackground'   => '#fdf3da',

      // Shade Green
      'sh-lightgreenborder'   => '#c6e6c7',
      'sh-greenborder'        => '#a0c4a1',
      'sh-greenicon'          => '#4ca74e',
      'sh-greentext'          => '#326d34',
      'sh-greenbackground'    => '#ddefdd',

      // Shade Blue
      'sh-lightblueborder'    => '#cfdbe3',
      'sh-blueborder'         => '#a7b5bf',
      'sh-blueicon'           => '#6b748c',
      'sh-bluetext'           => '#464c5c',
      'sh-bluebackground'     => '#dee7f8',

      // Shade Indigo
      'sh-lightindigoborder'  => '#d1c9ee',
      'sh-indigoborder'       => '#bcb4da',
      'sh-indigoicon'         => '#8672d4',
      'sh-indigotext'         => '#6e5cb6',
      'sh-indigobackground'   => '#eae6f7',

      // Shade Violet
      'sh-lightvioletborder'  => '#e0d1e7',
      'sh-violetborder'       => '#bcabc5',
      'sh-violeticon'         => '#9260ad',
      'sh-violettext'         => '#69427f',
      'sh-violetbackground'   => '#efe8f3',

      // Shade Pink
      'sh-lightpinkborder'  => '#f6d5ef',
      'sh-pinkborder'       => '#d5aecd',
      'sh-pinkicon'         => '#e26fcb',
      'sh-pinktext'         => '#da49be',
      'sh-pinkbackground'   => '#fbeaf8',

      // Shade Grey
      'sh-lightgreyborder'    => '#d8d8d8',
      'sh-greyborder'         => '#b2b2b2',
      'sh-greyicon'           => '#757575',
      'sh-greytext'           => '#555555',
      'sh-greybackground'     => '#e7e7e7',

      // Shade Disabled
      'sh-lightdisabledborder'  => '#e5e5e5',
      'sh-disabledborder'       => '#cbcbcb',
      'sh-disabledicon'         => '#bababa',
      'sh-disabledtext'         => '#a6a6a6',
      'sh-disabledbackground'   => '#f3f3f3',

    );
  }


  public function replaceCSSVariable($matches) {
    static $map;
    if (!$map) {
      $map = self::getCSSVariableMap();
    }

    $var_name = $matches[1];
    if (empty($map[$var_name])) {
      $path = $this->currentPath;
      throw new Exception(
        pht(
          "CSS file '%s' has unknown variable '%s'.",
          $path,
          $var_name));
    }

    return $map[$var_name];
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
