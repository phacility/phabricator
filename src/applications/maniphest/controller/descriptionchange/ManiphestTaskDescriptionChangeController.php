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

class ManiphestTaskDescriptionChangeController extends ManiphestController {

  private $transactionID;

  public function willProcessRequest(array $data) {
    $this->transactionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $transaction = id(new ManiphestTransaction())->load($this->transactionID);
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

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();

    $view = new ManiphestTransactionDetailView();
    $view->setTransactionGroup($transactions);
    $view->setHandles($handles);
    $view->setMarkupEngine($engine);
    $view->setRenderSummaryOnly(true);
    $view->setRenderFullSummary(true);

    return id(new AphrontAjaxResponse())->setContent($view->render());
  }

}
