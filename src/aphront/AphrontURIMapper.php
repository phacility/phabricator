<?php

final class AphrontURIMapper extends Phobject {

  private $map;

  public function __construct(array $map) {
    $this->map = $map;
  }

  public function mapPath($path) {
    $map = $this->map;
    foreach ($map as $rule => $value) {
      list($controller, $data) = $this->tryRule($rule, $value, $path);
      if ($controller) {
        foreach ($data as $k => $v) {
          if (is_numeric($k)) {
            unset($data[$k]);
          }
        }
        return array($controller, $data);
      }
    }

    return array(null, null);
  }

  private function tryRule($rule, $value, $path) {
    $match = null;
    $pattern = '#^'.$rule.(is_array($value) ? '' : '$').'#';
    if (!preg_match($pattern, $path, $match)) {
      return array(null, null);
    }

    if (!is_array($value)) {
      return array($value, $match);
    }

    $path = substr($path, strlen($match[0]));
    foreach ($value as $srule => $sval) {
      list($controller, $data) = $this->tryRule($srule, $sval, $path);
      if ($controller) {
        return array($controller, $data + $match);
      }
    }

    return array(null, null);
  }

}
