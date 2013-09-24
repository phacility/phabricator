<?php

/**
 * @group maniphest
 */
final class ManiphestTransactionPreviewController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $comments = $request->getStr('comments');

    $task = id(new ManiphestTask())->load($this->id);
    if (!$task) {
      return new Aphront404Response();
    }

    id(new PhabricatorDraft())
      ->setAuthorPHID($user->getPHID())
      ->setDraftKey($task->getPHID())
      ->setDraft($comments)
      ->replaceOrDelete();

    $action = $request->getStr('action');

    $transaction = new ManiphestTransaction();
    $transaction->setAuthorPHID($user->getPHID());
    $transaction->setTransactionType($action);

    // This should really be split into a separate transaction, but it should
    // all come out in the wash once we fully move to modern stuff.
    $transaction->getModernTransaction()->attachComment(
      id(new ManiphestTransactionComment())
        ->setContent($comments));

    $value = $request->getStr('value');
    // grab phids for handles and set transaction values based on action and
    // value (empty or control-specific format) coming in from the wire
    switch ($action) {
      case ManiphestTransactionType::TYPE_PRIORITY:
        $transaction->setOldValue($task->getPriority());
        $transaction->setNewValue($value);
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        if ($value) {
          $value = current(json_decode($value));
          $phids = array($value);
        } else {
          $phids = array();
        }
        $transaction->setNewValue($value);
        break;
      case ManiphestTransactionType::TYPE_CCS:
        if ($value) {
          $value = json_decode($value);
        }
        if (!$value) {
          $value = array();
        }
        $phids = $value;

        foreach ($task->getCCPHIDs() as $cc_phid) {
          $phids[] = $cc_phid;
          $value[] = $cc_phid;
        }

        $transaction->setOldValue($task->getCCPHIDs());
        $transaction->setNewValue($value);
        break;
      case ManiphestTransactionType::TYPE_PROJECTS:
        if ($value) {
          $value = json_decode($value);
        }
        if (!$value) {
          $value = array();
        }

        $phids = $value;
        foreach ($task->getProjectPHIDs() as $project_phid) {
          $phids[] = $project_phid;
          $value[] = $project_phid;
        }

        $transaction->setOldValue($task->getProjectPHIDs());
        $transaction->setNewValue($value);
        break;
      default:
        $phids = array();
        $transaction->setNewValue($value);
        break;
    }
    $phids[] = $user->getPHID();

    $handles = $this->loadViewerHandles($phids);

    $transactions = array();
    $transactions[] = $transaction;

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    if ($transaction->getModernTransaction()->hasComment()) {
      $engine->addObject(
        $transaction->getModernTransaction()->getComment(),
        PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
    }
    $engine->process();

    $transaction->getModernTransaction()->setHandles($handles);

    $view = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setTransactions(mpull($transactions, 'getModernTransaction'))
      ->setIsPreview(true)
      ->setIsDetailView(true);

    return id(new AphrontAjaxResponse())
      ->setContent((string)phutil_implode_html('', $view->buildEvents()));
  }

}
