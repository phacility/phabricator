<?php

/**
 * Allow infrastructure to apply transactions to the implementing object.
 *
 * For example, implementing this interface allows Subscriptions to apply CC
 * transactions, and allows Harbormaster to apply build result notifications.
 */
interface PhabricatorApplicationTransactionInterface {

  /**
   * Return a @{class:PhabricatorApplicationTransactionEditor} which can be
   * used to apply transactions to this object.
   *
   * @return PhabricatorApplicationTransactionEditor Editor for this object.
   */
  public function getApplicationTransactionEditor();


  /**
   * Return a template transaction for this object.
   *
   * @return PhabricatorApplicationTransaction
   */
  public function getApplicationTransactionTemplate();

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */
/*

  public function getApplicationTransactionEditor() {
    return new <<<???>>>Editor();
  }

  public function getApplicationTransactionTemplate() {
    return new <<<???>>>Transaction();
  }

*/
