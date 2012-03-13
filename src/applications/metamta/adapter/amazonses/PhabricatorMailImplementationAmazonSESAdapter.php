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

final class PhabricatorMailImplementationAmazonSESAdapter
  extends PhabricatorMailImplementationPHPMailerLiteAdapter {

  private $message;
  private $isHTML;

  public function __construct() {
    parent::__construct();
    $this->mailer->Mailer = 'amazon-ses';
    $this->mailer->customMailer = $this;
  }

  public function supportsMessageIDHeader() {
    // Amazon SES will ignore any Message-ID we provide.
    return false;
  }

  public function executeSend($body) {
    $key = PhabricatorEnv::getEnvConfig('amazon-ses.access-key');
    $secret = PhabricatorEnv::getEnvConfig('amazon-ses.secret-key');

    $root = phutil_get_library_root('phabricator');
    $root = dirname($root);
    require_once $root.'/externals/amazon-ses/ses.php';

    $service = newv('SimpleEmailService', array($key, $secret));
    $service->enableUseExceptions(true);
    return $service->sendRawEmail($body);
  }

}
