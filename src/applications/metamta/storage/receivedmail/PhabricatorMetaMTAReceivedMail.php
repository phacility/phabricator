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

class PhabricatorMetaMTAReceivedMail extends PhabricatorMetaMTADAO {

  protected $headers = array();
  protected $bodies = array();
  protected $attachments = array();

  protected $relatedPHID;
  protected $authorPHID;
  protected $message;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'headers'     => self::SERIALIZATION_JSON,
        'bodies'      => self::SERIALIZATION_JSON,
        'attachments' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function processReceivedMail() {
    $to = idx($this->headers, 'to');

    // Accept a match either at the beginning of the address or after an open
    // angle bracket, as in:
    //    "some display name" <D1+xyz+asdf@example.com>
    $matches = null;
    $ok = preg_match(
      '/(?:^|<)((?:D|T)\d+)\+(\d+)\+([a-f0-9]{16})@/U',
      $to,
      $matches);

    if (!$ok) {
      return $this->setMessage("Unrecognized 'to' format: {$to}")->save();
    }

    $receiver_name = $matches[1];
    $user_id = $matches[2];
    $hash = $matches[3];

    $user = id(new PhabricatorUser())->load($user_id);
    if (!$user) {
      return $this->setMessage("Invalid user '{$user_id}'")->save();
    }

    if ($user->getIsDisabled()) {
      return $this->setMessage("User '{$user_id}' is disabled")->save();
    }

    $this->setAuthorPHID($user->getPHID());

    $receiver = self::loadReceiverObject($receiver_name);
    if (!$receiver) {
      return $this->setMessage("Invalid object '{$receiver_name}'")->save();
    }

    $this->setRelatedPHID($receiver->getPHID());

    $expect_hash = self::computeMailHash(
      $receiver->getMailKey(),
      $user->getPHID());
    if ($expect_hash != $hash) {
      return $this->setMessage("Invalid mail hash!")->save();
    }

    if ($receiver instanceof ManiphestTask) {
      $editor = new ManiphestTransactionEditor();
      $handler = $editor->buildReplyHandler($receiver);
    } else if ($receiver instanceof DifferentialRevision) {
      $handler = DifferentialMail::newReplyHandlerForRevision($receiver);
    }

    $handler->setActor($user);
    $handler->receiveEmail($this);

    $this->setMessage('OK');

    return $this->save();
  }

  public function getCleanTextBody() {
    $body = idx($this->bodies, 'text');

    // TODO: Refine this "algorithm".

    $lines = explode("\n", trim($body));
    for ($ii = 0; $ii < count($lines); $ii++) {
      if (preg_match('/^\s*On\b.*\bwrote:\s*$/', $lines[$ii])) {
        $lines = array_slice($lines, 0, $ii);
        break;
      }
    }

    return trim(implode("\n", $lines));
  }

  public static function loadReceiverObject($receiver_name) {
    if (!$receiver_name) {
      return null;
    }

    $receiver_type = $receiver_name[0];
    $receiver_id   = substr($receiver_name, 1);

    $class_obj = null;
    switch ($receiver_type) {
      case 'T':
        $class_obj = newv('ManiphestTask', array());
        break;
      case 'D':
        $class_obj = newv('DifferentialRevision', array());
        break;
      default:
        return null;
    }

    return $class_obj->load($receiver_id);
  }

  public static function computeMailHash($mail_key, $phid) {
    $global_mail_key = PhabricatorEnv::getEnvConfig('phabricator.mail-key');

    $hash = sha1($mail_key.$global_mail_key.$phid);
    return substr($hash, 0, 16);
  }


}
