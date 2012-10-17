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

function javelin_render_tag(
  $tag,
  array $attributes = array(),
  $content = null) {

  if (isset($attributes['sigil']) ||
      isset($attributes['meta'])  ||
      isset($attributes['mustcapture'])) {
    foreach ($attributes as $k => $v) {
      switch ($k) {
        case 'sigil':
          $attributes['data-sigil'] = $v;
          unset($attributes[$k]);
          break;
        case 'meta':
          $response = CelerityAPI::getStaticResourceResponse();
          $id = $response->addMetadata($v);
          $attributes['data-meta'] = $id;
          unset($attributes[$k]);
          break;
        case 'mustcapture':
          if ($v) {
            $attributes['data-mustcapture'] = '1';
          } else {
            unset($attributes['data-mustcapture']);
          }
          unset($attributes[$k]);
          break;
      }
    }
  }

  return phutil_render_tag($tag, $attributes, $content);
}


function phabricator_render_form(PhabricatorUser $user, $attributes, $content) {
  if (strcasecmp(idx($attributes, 'method'), 'POST') == 0 &&
      !preg_match('#^(https?:|//)#', idx($attributes, 'action'))) {
    $content =
      phutil_render_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => AphrontRequest::getCSRFTokenName(),
          'value' => $user->getCSRFToken(),
        )).
      phutil_render_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => '__form__',
          'value' => true,
        )).
      $content;
  }
  return javelin_render_tag('form', $attributes, $content);
}

