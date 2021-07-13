<?php


class ReviewersTransaction {
  private $oldValue;
  private $newValue;

  public function __construct(DifferentialTransaction $tx) {
    $this->oldValue = $tx->getOldValue();
    $this->newValue = $tx->getNewValue();
  }

  public function getAllUsers(): array
  {
    return array_unique(array_merge(array_keys($this->oldValue), array_keys($this->newValue)));
  }

  public function getReviewerStatus(string $userPHID): ?string
  {
    // The extra "?? null" at the end is to suppress the PHP "undefined array key" error
    $rawStatus = $this->newValue[$userPHID] ?? $this->oldValue[$userPHID] ?? null; // get first non-null value;
    if (is_null($rawStatus)) {
      return null;
    }

    return EmailReviewer::translateStatus($rawStatus);
  }

  public function getAllReviewerStatuses(): array {
    return array_values($this->newValue);
  }

  public function getReviewerChange(string $userPHID): string
  {
    $old = $this->oldValue[$userPHID] ?? null;
    $new = $this->newValue[$userPHID] ?? null;

    if ($old and !$new) {
      $change = 'removed';
    } else if (!$old and $new) {
      $change = 'added';
    } else {
      $change = 'no-change';
    }

    return $change;
  }
}