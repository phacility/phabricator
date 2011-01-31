<?php

/*
 * Copyright 2011 Facebook, Inc.
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

function javelin_render_tag(
  $tag,
  array $attributes = array(),
  $content = null) {

  if (isset($attributes['sigil']) ||
      isset($attributes['meta'])  ||
      isset($attributes['mustcapture'])) {
    $classes = array();
    foreach ($attributes as $k => $v) {
      switch ($k) {
        case 'sigil':
          $classes[] = 'FN_'.$v;
          unset($attributes[$k]);
          break;
        case 'meta':
          $response = CelerityAPI::getStaticResourceResponse();
          $id = $response->addMetadata($v);
          $classes[] = 'FD_'.$id;
          unset($attributes[$k]);
          break;
        case 'mustcapture':
          $classes[] = 'FI_CAPTURE';
          unset($attributes[$k]);
          break;
      }
    }

    if (isset($attributes['class'])) {
      $classes[] = $attributes['class'];
    }

    $attributes['class'] = implode(' ', $classes);
  }

  return phutil_render_tag($tag, $attributes, $content);
}
