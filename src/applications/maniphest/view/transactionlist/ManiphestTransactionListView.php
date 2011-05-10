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

class ManiphestTransactionListView extends AphrontView {

  private $transactions;
  private $handles;
  private $user;
  private $markupEngine;
  private $preview;

  public function setTransactions(array $transactions) {
    $this->transactions = $transactions;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setMarkupEngine(PhutilMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function render() {

    $views = array();


    $last = null;
    $group = array();
    $groups = array();
    foreach ($this->transactions as $transaction) {
      if ($last === null) {
        $last = $transaction;
        $group[] = $transaction;
        continue;
      } else if ($last->canGroupWith($transaction)) {
        $group[] = $transaction;
        if ($transaction->hasComments()) {
          $last = $transaction;
        }
      } else {
        $groups[] = $group;
        $last = $transaction;
        $group = array($transaction);
      }
    }
    if ($group) {
      $groups[] = $group;
    }

    foreach ($groups as $group) {
      $view = new ManiphestTransactionDetailView();
      $view->setTransactionGroup($group);
      $view->setHandles($this->handles);
      $view->setMarkupEngine($this->markupEngine);
      $view->setPreview($this->preview);
      $views[] = $view->render();
    }

    return
      '<div style="padding: .5em 1.5em;">'.
        implode("\n", $views).
      '</div>';
  }

}
