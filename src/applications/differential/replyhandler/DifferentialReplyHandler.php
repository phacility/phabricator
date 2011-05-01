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

class DifferentialReplyHandler {
  protected $revision;
  protected $actor;

  /*
   * Generate text like the following from the supported commands.
   * "
   *
   * ACTIONS
   * Reply to comment, or !accept, !reject, !abandon, !resign, !reclaim.
   *
   * "
   */
  public function getBodyText() {
    $supported_commands = $this->getSupportedCommands();
    $text = '';
    if (empty($supported_commands)) {
      return $text;
    }

    $comment_command_printed = false;
    $text .= "\nACTIONS\n";
    if (in_array(DifferentialAction::ACTION_COMMENT, $supported_commands)) {
      $text .= 'Reply to comment';
      $comment_command_printed = true;

      $supported_commands = array_diff(
        $supported_commands, array(DifferentialAction::ACTION_COMMENT));
    }

    if (!empty($supported_commands)) {
      if ($comment_command_printed) {
        $text .= ', or ';
      }

      $modified_commands = array();
      foreach ($supported_commands as $command) {
        $modified_commands[] = '!'.$command;
      }

      $text .= implode(', ', $modified_commands);
    }

    $text .= ".\n\n";

    return $text;
  }

  public function getSupportedCommands() {
    return array(
      DifferentialAction::ACTION_COMMENT,
      DifferentialAction::ACTION_ACCEPT,
      DifferentialAction::ACTION_REJECT,
      DifferentialAction::ACTION_ABANDON,
      DifferentialAction::ACTION_RECLAIM,
      DifferentialAction::ACTION_RESIGN,
    );
  }

  public function getReplyHandlerEmailAddress() {
    if (!self::isEnabled()) {
      return null;
    }

    $revision = $this->getRevision();
    if (!$revision) {
      return null;
    }

    return '...'; // TODO: build the D1234+92+aldsbn@domain.com as per D226
  }

  public function handleAction($body) {
    // all commands start with a bang and separated from the body by a newline
    // to make sure that actual feedback text couldn't trigger an action.
    // unrecognized commands will be parsed as part of the comment.
    $command = DifferentialAction::ACTION_COMMENT;
    $supported_commands = $this->getSupportedCommands();
    $regex = "/\A\n*!(" . implode('|', $supported_commands) . ")\n*/";
    $matches = array();
    if (preg_match($regex, $body, $matches)) {
      $command = $matches[1];
      $body = trim(str_replace('!' . $command, '', $body));
    }

    $actor = $this->getActor();
    if (!$actor) {
      throw new Exception('No actor is set for the reply action.');
    }

    try {
      $editor = new DifferentialCommentEditor(
        $this->getRevision(),
        $actor->getPHID(),
        $command);

      $editor->setMessage($body);
      $editor->setAddCC(($command != DifferentialAction::ACTION_RESIGN));
      $comment = $editor->save();

      return $comment->getID();

    } catch (Exception $ex) {
      $exception_mail = new DifferentialExceptionMail(
        $this->getRevision(),
        $ex,
        $body);

      $exception_mail->setToPHIDs(array($this->getActor()->getPHID()));
      $exception_mail->send();

      throw $ex;
    }
  }

  public function setActor(PhabricatorUser $actor) {
    $this->actor = $actor;
    return $this;
  }

  public function getActor() {
    return $this->actor;
  }

  public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

  public static function isEnabled() {
    return PhabricatorEnv::getEnvConfig('phabricator.enable-reply-handling');
  }

}
