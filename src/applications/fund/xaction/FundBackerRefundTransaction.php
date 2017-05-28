<?php

final class FundBackerRefundTransaction
  extends FundBackerTransactionType {

  const TRANSACTIONTYPE = 'fund:backer:refund';

  public function generateOldValue($object) {
    return null;
  }


}
