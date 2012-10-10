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

class DifferentialReplyHandler extends PhabricatorMailReplyHandler {

  private $receivedMail;

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof DifferentialRevision)) {
      throw new Exception("Receiver is not a DifferentialRevision!");
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'D');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('D');
  }

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig(
      'metamta.differential.reply-handler-domain');
  }

  /*
   * Generate text like the following from the supported commands.
   * "
   *
   * ACTIONS
   * Reply to comment, or !accept, !reject, !abandon, !resign, !reclaim.
   *
   * "
   */
  public function getReplyHandlerInstructions() {
    if (!$this->supportsReplies()) {
      return null;
    }

    $supported_commands = $this->getSupportedCommands();
    $text = '';
    if (empty($supported_commands)) {
      return $text;
    }

    $comment_command_printed = false;
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

    $text .= ".";

    return $text;
  }

  public function getSupportedCommands() {
    $actions = array(
      DifferentialAction::ACTION_COMMENT,
      DifferentialAction::ACTION_REJECT,
      DifferentialAction::ACTION_ABANDON,
      DifferentialAction::ACTION_RECLAIM,
      DifferentialAction::ACTION_RESIGN,
      DifferentialAction::ACTION_RETHINK,
      'unsubscribe',
    );

    if (PhabricatorEnv::getEnvConfig('differential.enable-email-accept')) {
      $actions[] = DifferentialAction::ACTION_ACCEPT;
    }

    return $actions;
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $this->receivedMail = $mail;
    $this->handleAction($mail->getCleanTextBody());
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

    switch ($command) {
      case 'unsubscribe':
        $this->unsubscribeUser($this->getMailReceiver(), $actor);
        // TODO: Send the user a confirmation email?
        return null;
    }

    try {
      $editor = new DifferentialCommentEditor(
        $this->getMailReceiver(),
        $command);
      $editor->setActor($actor);
      $editor->setExcludeMailRecipientPHIDs(
        $this->getExcludeMailRecipientPHIDs());

      // NOTE: We have to be careful about this because Facebook's
      // implementation jumps straight into handleAction() and will not have
      // a PhabricatorMetaMTAReceivedMail object.
      if ($this->receivedMail) {
        $content_source = PhabricatorContentSource::newForSource(
          PhabricatorContentSource::SOURCE_EMAIL,
          array(
            'id' => $this->receivedMail->getID(),
          ));
        $editor->setContentSource($content_source);
        $editor->setParentMessageID($this->receivedMail->getMessageID());
      }
      $editor->setMessage($body);
      $comment = $editor->save();

      return $comment->getID();

    } catch (Exception $ex) {
      $exception_mail = new DifferentialExceptionMail(
        $this->getMailReceiver(),
        $ex,
        $this->receivedMail->getRawTextBody());

      $exception_mail->setToPHIDs(array($this->getActor()->getPHID()));
      $exception_mail->send();

      throw $ex;
    }
  }

  private function unsubscribeUser(
    DifferentialRevision $revision,
    PhabricatorUser $user) {

    $revision->loadRelationships();
    DifferentialRevisionEditor::removeCCAndUpdateRevision(
      $revision,
      $user->getPHID(),
      $user->getPHID());
  }


}
