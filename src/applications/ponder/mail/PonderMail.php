<?php

abstract class PonderMail {

  protected $to = array();
  protected $actorHandle;
  protected $question;
  protected $target;

  protected $isFirstMailAboutQuestion;

  // protected $replyHandler;

  protected $parentMessageID;

  protected function renderSubject() {
    $question = $this->getQuestion();
    $title = $question->getTitle();
    $id = $question->getID();
    return "Q{$id}: {$title}";
  }

  abstract protected function renderVaryPrefix();
  abstract protected function renderBody();

  public function setActorHandle($actor_handle) {
    $this->actorHandle = $actor_handle;
    return $this;
  }

  public function getActorHandle() {
    return $this->actorHandle;
  }

  protected function getActorName() {
    return $this->actorHandle->getRealName();
  }

  protected function getSubjectPrefix() {
    return "[Ponder]";
  }

  public function setToPHIDs(array $to) {
    $this->to = $to;
    return $this;
  }

  protected function getToPHIDs() {
    return $this->to;
  }

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function getQuestion() {
    return $this->question;
  }

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function getTarget() {
    return $this->target;
  }

  protected function getThreadID() {
    $phid = $this->getQuestion()->getPHID();
    return "ponder-ques-{$phid}";
  }

  protected function getThreadTopic() {
    $id = $this->getQuestion()->getID();
    $title = $this->getQuestion()->getTitle();
    return "Q{$id}: {$title}";
  }

  public function send() {
    $email_to = array_filter(array_unique($this->to));
    $question = $this->getQuestion();
    $target = $this->getTarget();

    $uri = PhabricatorEnv::getURI('/Q'. $question->getID());
    $thread_id = $this->getThreadID();

    $handles = id(new PhabricatorObjectHandleData($email_to))
      ->loadHandles();

    $reply_handler = new PonderReplyHandler();
    $reply_handler->setMailReceiver($question);

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($this->renderBody());
    $body->addTextSection(pht('QUESTION DETAIL'), $uri);

    $template = id(new PhabricatorMetaMTAMail())
      ->setSubject($this->getThreadTopic())
      ->setSubjectPrefix($this->getSubjectPrefix())
      ->setVarySubjectPrefix($this->renderVaryPrefix())
      ->setFrom($target->getAuthorPHID())
      ->setParentMessageID($this->parentMessageID)
      ->addHeader('Thread-Topic', $this->getThreadTopic())
      ->setThreadID($this->getThreadID(), false)
      ->setRelatedPHID($question->getPHID())
      ->setIsBulk(true)
      ->setBody($body->render());

    $mails = $reply_handler->multiplexMail(
      $template,
      array_select_keys($handles, $email_to),
      array());

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  protected function formatText($text) {
    $text = explode("\n", rtrim($text));
    foreach ($text as &$line) {
      $line = rtrim('  '.$line);
    }
    unset($line);
    return implode("\n", $text);
  }
}
