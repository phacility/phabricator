<?php

/**
 * @group maniphest
 */
final class ManiphestTaskDescriptionChangeController
  extends ManiphestController {

  private $transactionID;

  protected function setTransactionID($transaction_id) {
    $this->transactionID = $transaction_id;
    return $this;
  }

  public function getTransactionID() {
    return $this->transactionID;
  }

  public function willProcessRequest(array $data) {
    $this->setTransactionID(idx($data, 'id'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $is_show_more = false;
    if (!$this->getTransactionID()) {
      $this->setTransactionID($this->getRequest()->getStr('ref'));
      $is_show_more = true;
    }

    $transaction_id = $this->getTransactionID();
    $transaction = id(new ManiphestTransaction())->load($transaction_id);
    if (!$transaction) {
      return new Aphront404Response();
    }

    $transactions = array($transaction);

    $phids = array();
    foreach ($transactions as $xaction) {
      foreach ($xaction->extractPHIDs() as $phid) {
        $phids[$phid] = $phid;
      }
    }
    $handles = $this->loadViewerHandles($phids);

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    $engine->addObject($transaction, ManiphestTransaction::MARKUP_FIELD_BODY);
    $engine->process();

    $view = new ManiphestTransactionDetailView();
    $view->setTransactionGroup($transactions);
    $view->setHandles($handles);
    $view->setUser($user);
    $view->setMarkupEngine($engine);
    $view->setRenderSummaryOnly(true);
    $view->setRenderFullSummary(true);
    $view->setRangeSpecification($request->getStr('range'));

    if ($is_show_more) {
      return id(new PhabricatorChangesetResponse())
        ->setRenderedChangeset($view->render());
    } else {
      return id(new AphrontAjaxResponse())->setContent($view->render());
    }
  }

}
