<?php

final class ManiphestTransactionPreviewController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $comments = $request->getStr('comments');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    id(new PhabricatorDraft())
      ->setAuthorPHID($viewer->getPHID())
      ->setDraftKey($task->getPHID())
      ->setDraft($comments)
      ->replaceOrDelete();

    $action = $request->getStr('action');

    $transaction = new ManiphestTransaction();
    $transaction->setAuthorPHID($viewer->getPHID());
    $transaction->setTransactionType($action);

    // This should really be split into a separate transaction, but it should
    // all come out in the wash once we fully move to modern stuff.
    $transaction->attachComment(
      id(new ManiphestTransactionComment())
        ->setContent($comments));

    $value = $request->getStr('value');
    // grab phids for handles and set transaction values based on action and
    // value (empty or control-specific format) coming in from the wire
    switch ($action) {
      case ManiphestTransaction::TYPE_PRIORITY:
        $transaction->setOldValue($task->getPriority());
        $transaction->setNewValue($value);
        break;
      case ManiphestTransaction::TYPE_OWNER:
        if ($value) {
          $value = current(json_decode($value));
          $phids = array($value);
        } else {
          $phids = array();
        }
        $transaction->setNewValue($value);
        break;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
        if ($value) {
          $value = json_decode($value);
        }
        if (!$value) {
          $value = array();
        }
        $phids = array();
        foreach ($value as $cc_phid) {
          $phids[] = $cc_phid;
        }
        $transaction->setOldValue(array());
        $transaction->setNewValue($phids);
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        if ($value) {
          $value = phutil_json_decode($value);
        }
        if (!$value) {
          $value = array();
        }

        $phids = array();
        $value = array_fuse($value);
        foreach ($value as $project_phid) {
          $phids[] = $project_phid;
          $value[$project_phid] = array('dst' => $project_phid);
        }

        $project_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $transaction
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $project_type)
          ->setOldValue(array())
          ->setNewValue($value);
        break;
      case ManiphestTransaction::TYPE_STATUS:
        $phids = array();
        $transaction->setOldValue($task->getStatus());
        $transaction->setNewValue($value);
        break;
      default:
        $phids = array();
        $transaction->setNewValue($value);
        break;
    }
    $phids[] = $viewer->getPHID();

    $handles = $this->loadViewerHandles($phids);

    $transactions = array();
    $transactions[] = $transaction;

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    $engine->setContextObject($task);
    if ($transaction->hasComment()) {
      $engine->addObject(
        $transaction->getComment(),
        PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
    }
    $engine->process();

    $transaction->setHandles($handles);

    $view = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setTransactions($transactions)
      ->setIsPreview(true);

    return id(new AphrontAjaxResponse())
      ->setContent((string)phutil_implode_html('', $view->buildEvents()));
  }

}
