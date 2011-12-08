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

/**
 * @group maniphest
 */
class ManiphestTaskDescriptionChangeController extends ManiphestController {

  private $transactionID;

  private function setTransactionID($transaction_id) {
    $this->transactionID = $transaction_id;
    return $this;
  }

  public function getTransactionID() {
    return $this->transactionID;
  }

  public function willProcessRequest(array $data) {
    $this->setTransactionID($data['id']);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    // this means we're using "show more" on a diff of a description and
    // should thus use the rendering reference to identify the transaction
    $ref = $request->getStr('ref');
    if ($ref) {
      $this->setTransactionID($ref);
    }

    $transaction_id = $this->getTransactionID();
    $transaction = id(new ManiphestTransaction())->load($transaction_id);
    if (!$transaction) {
      return new Aphront404Response();
    }

    $transactions = array($transaction);

    $phids = array();
    foreach ($transactions as $transaction) {
      foreach ($transaction->extractPHIDs() as $phid) {
        $phids[$phid] = $phid;
      }
    }
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $engine = PhabricatorMarkupEngine::newManiphestMarkupEngine();

    $view = new ManiphestTransactionDetailView();
    $view->setTransactionGroup($transactions);
    $view->setHandles($handles);
    $view->setUser($user);
    $view->setMarkupEngine($engine);
    $view->setRenderSummaryOnly(true);
    $view->setRenderFullSummary(true);
    $view->setRangeSpecification($request->getStr('range'));

    return id(new AphrontAjaxResponse())->setContent($view->render());
  }

}
