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

class PhabricatorFileAltViewController extends PhabricatorFileController {

  private $phid;
  private $key;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->key  = $data['key'];
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {

    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    if (!$alt) {
      return new Aphront400Response();
    }

    $request = $this->getRequest();

    $alt_domain = id(new PhutilURI($alt))->getDomain();
    if ($alt_domain != $request->getHost()) {
      return new Aphront400Response();
    }

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $this->phid);
    if (!$file) {
      return new Aphront404Response();
    }

    if (!$file->validateSecretKey($this->key)) {
      return new Aphront404Response();
    }

    // It's safe to bypass view restrictions because we know we are being served
    // off an alternate domain which we will not set cookies on.

    $data = $file->loadFileData();
    $response = new AphrontFileResponse();
    $response->setContent($data);
    $response->setMimeType($file->getMimeType());
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);

    return $response;
  }
}
