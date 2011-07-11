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

class PhrictionDocument extends PhrictionDAO {

  protected $id;
  protected $phid;
  protected $slug;
  protected $depth;
  protected $contentID;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_WIKI);
  }

  public static function getSlugURI($slug) {
    if ($slug == '/') {
      return '/w/';
    } else {
      return '/w/'.$slug;
    }
  }

  public static function normalizeSlug($slug) {

    // TODO: We need to deal with unicode at some point, this is just a very
    // basic proof-of-concept implementation.

    $slug = strtolower($slug);
    $slug = preg_replace('@/+@', '/', $slug);
    $slug = trim($slug, '/');
    $slug = preg_replace('@[^a-z0-9/]+@', '_', $slug);
    $slug = trim($slug, '_');

    return $slug.'/';
  }

  public function setSlug($slug) {
    $this->slug   = self::normalizeSlug($slug);
    $this->depth  = substr_count($this->slug, '/');
    return $this;
  }

}
