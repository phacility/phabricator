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

final class PhabricatorFileURI {

  public static function getViewURIForPHID($phid) {

    // TODO: Get rid of this class, the advent of the applet attack makes the
    // tiny optimization it represented effectively obsolete.

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $phid);
    if ($file) {
      return $file->getViewURI();
    }

    return null;
  }

}
