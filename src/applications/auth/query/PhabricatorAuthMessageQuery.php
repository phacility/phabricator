<?php

final class PhabricatorAuthMessageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $messageKeys;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMessageKeys(array $keys) {
    $this->messageKeys = $keys;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorAuthMessage();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->messageKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'messageKey IN (%Ls)',
        $this->messageKeys);
    }

    return $where;
  }

  protected function willFilterPage(array $messages) {
    $message_types = PhabricatorAuthMessageType::getAllMessageTypes();

    foreach ($messages as $key => $message) {
      $message_key = $message->getMessageKey();

      $message_type = idx($message_types, $message_key);
      if (!$message_type) {
        unset($messages[$key]);
        $this->didRejectResult($message);
        continue;
      }

      $message->attachMessageType($message_type);
    }

    return $messages;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAuthApplication';
  }

}
