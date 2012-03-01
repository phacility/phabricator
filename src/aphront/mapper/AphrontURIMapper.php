<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group aphront
 */
final class AphrontURIMapper {

  private $map;

  final public function __construct(array $map) {
    $this->map = $map;
  }

  final public function mapPath($path) {
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

  final private function tryRule($rule, $value, $path) {
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
