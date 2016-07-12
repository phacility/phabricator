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
   * Return the object to apply transactions to. Normally this is the current
   * object (that is, `$this`), but in some cases transactions may apply to
   * a different object: for example, @{class:DifferentialDiff} applies
   * transactions to the associated @{class:DifferentialRevision}.
   *
   * @return PhabricatorLiskDAO Object to apply transactions to.
   */
  public function getApplicationTransactionObject();


  /**
   * Return a template transaction for this object.
   *
   * @return PhabricatorApplicationTransaction
   */
  public function getApplicationTransactionTemplate();

  /**
   * Hook to augment the $timeline with additional data for rendering.
   *
   * @return PhabricatorApplicationTransactionView
   */
  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request);

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */
/*

  public function getApplicationTransactionEditor() {
    return new <<<???>>>Editor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new <<<???>>>Transaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

*/
