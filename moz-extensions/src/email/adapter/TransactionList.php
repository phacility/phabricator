<?php


class TransactionList {
  /** @var DifferentialTransaction[] */
  private array $transactions;

  /**
   * @param DifferentialTransaction[] $transactions
   */
  public function __construct(array $transactions) {
    $this->transactions = $transactions;
  }

  public function getAnyTransaction(): DifferentialTransaction {
    return current($this->transactions);
  }

  public function getFirstTransaction(): DifferentialTransaction {
    $lowest = null;
    foreach ($this->transactions as $transaction) {
      if (!$lowest || $transaction->getID() < $lowest->getID()) {
        $lowest = $transaction;
      }
    }
    return $lowest;
  }

  public function attemptGetTransactionWithType($type): ?DifferentialTransaction {
    $matching = $this->getAllTransactionsWithType($type);

    if (count($matching) > 1) {
      throw new RuntimeException('Too many transactions match type');
    } else if (count($matching) == 0) {
      return null;
    }
    return current($matching);
  }

  public function getTransactionWithType(string $type): DifferentialTransaction {
    $transaction = $this->attemptGetTransactionWithType($type);
    if (!$transaction) {
      throw new RuntimeException('Expected a transaction to match type');
    }
    return $transaction;
  }

  /**
   * @return DifferentialTransaction[]
   */
  public function getAllTransactionsWithType(string $type): array
  {
    return array_filter($this->transactions, function($transaction) use ($type) {
      return $transaction->getTransactionType() == $type;
    });
  }

  public function containsType(string $type): bool
  {
    return !empty(array_filter($this->transactions, function ($transaction) use ($type) {
      return $transaction->getTransactionType() == $type;
    }));
  }

  /**
   * @param string[] $types
   * @return bool
   */
  public function containsOneOfType(array $types): bool
  {
    return !empty(array_filter($this->transactions, function ($transaction) use ($types) {
      return in_array($transaction->getTransactionType(), $types);
    }));
  }

  /**
   * @param string[] $types
   * @return bool
   */
  public function containsAllOfTypes(array $types): bool
  {
    return empty(array_filter($types, function ($type) {
      return !$this->containsType($type);
    }));
  }
}