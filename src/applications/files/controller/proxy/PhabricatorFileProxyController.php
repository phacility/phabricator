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

class PhabricatorFileProxyController extends PhabricatorFileController {

  private $uri;

  public function processRequest() {

    if (!PhabricatorEnv::getEnvConfig('files.enable-proxy')) {
      return new Aphront400Response();
    }

    $request = $this->getRequest();
    $uri = $request->getStr('uri');

    $proxy = id(new PhabricatorFileProxyImage())->loadOneWhere(
      'uri = %s',
      $uri);

    if (!$proxy) {
      // This write is fine to skip CSRF checks for, we're just building a
      // cache of some remote image.
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $file = PhabricatorFile::newFromFileDownload(
        $uri,
        nonempty(basename($uri), 'proxied-file'));
      if ($file) {
        $proxy = new PhabricatorFileProxyImage();
        $proxy->setURI($uri);
        $proxy->setFilePHID($file->getPHID());
        $proxy->save();
      }

      unset($unguarded);
    }

    if ($proxy) {
      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s',
                                                      $proxy->getFilePHID());
      if ($file) {
        $view_uri = $file->getBestURI();
      } else {
        $bad_phid = $proxy->getFilePHID();
        throw new Exception(
          "Unable to load file with phid {$bad_phid}."
        );
      }
      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    return new Aphront400Response();
  }
}
