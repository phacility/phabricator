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

final class PhabricatorSlug {

  public static function normalize($slug) {

    // TODO: We need to deal with unicode at some point, this is just a very
    // basic proof-of-concept implementation.

    $slug = strtolower($slug);
    $slug = preg_replace('@/+@', '/', $slug);
    $slug = trim($slug, '/');
    $slug = preg_replace('@[^a-z0-9/]+@', '_', $slug);
    $slug = trim($slug, '_');

    return $slug.'/';
  }

  public static function getDefaultTitle($slug) {
    $parts = explode('/', trim($slug, '/'));
    $default_title = end($parts);
    $default_title = str_replace('_', ' ', $default_title);
    $default_title = ucwords($default_title);
    $default_title = nonempty($default_title, 'Untitled Document');
    return $default_title;
  }

  public static function getAncestry($slug) {
    $slug = self::normalize($slug);

    if ($slug == '/') {
      return array();
    }

    $ancestors = array(
      '/',
    );

    $slug = explode('/', $slug);
    array_pop($slug);
    array_pop($slug);

    $accumulate = '';
    foreach ($slug as $part) {
      $accumulate .= $part.'/';
      $ancestors[] = $accumulate;
    }

    return $ancestors;
  }

  public static function getDepth($slug) {
    $slug = self::normalize($slug);
    if ($slug == '/') {
      return 0;
    } else {
      return substr_count($slug, '/');
    }
  }

}
