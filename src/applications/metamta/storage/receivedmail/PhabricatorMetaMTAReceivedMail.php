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

    $matches = null;
    $ok = preg_match(
      '/^((?:D|T)\d+)\+(\d+)\+([a-f0-9]{16})@/',
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

    $this->setAuthorPHID($user->getPHID());

    $receiver = self::loadReceiverObject($receiver_name);
    if (!$receiver) {
      return $this->setMessage("Invalid object '{$receiver_name}'")->save();
    }

    $this->setRelatedPHID($receiver->getPHID());

    $expect_hash = self::computeMailHash($receiver, $user);
    if ($expect_hash != $hash) {
      return $this->setMessage("Invalid mail hash!")->save();
    }

    // TODO: Move this into the application logic instead.
    if ($receiver instanceof ManiphestTask) {
      $this->processManiphestMail($receiver, $user);
    } else if ($receiver instanceof DifferentialRevision) {
      $this->processDifferentialMail($receiver, $user);
    }

    $this->setMessage('OK');

    return $this->save();
  }

  private function processManiphestMail(
    ManiphestTask $task,
    PhabricatorUser $user) {

    // TODO: implement this

  }

  private function processDifferentialMail(
    DifferentialRevision $revision,
    PhabricatorUser $user) {

    // TODO: Support actions

    $editor = new DifferentialCommentEditor(
      $revision,
      $user->getPHID(),
      DifferentialAction::ACTION_COMMENT);
    $editor->setMessage($this->getCleanTextBody());
    $editor->save();

  }

  private function getCleanTextBody() {
    $body = idx($this->bodies, 'text');

    // TODO: Detect quoted content and exclude it.

    return $body;
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

  public static function computeMailHash(
    $mail_receiver,
    PhabricatorUser $user) {
    $global_mail_key = PhabricatorEnv::getEnvConfig('phabricator.mail-key');
    $local_mail_key = $mail_receiver->getMailKey();

    $hash = sha1($local_mail_key.$global_mail_key.$user->getPHID());
    return substr($hash, 0, 16);
  }


}
