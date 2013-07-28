<?php

final class PonderQuestionViewController extends PonderController {

  private $questionID;

  public function willProcessRequest(array $data) {
    $this->questionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $question = PonderQuestionQuery::loadSingle($user, $this->questionID);
    if (!$question) {
      return new Aphront404Response();
    }
    $question->attachRelated();
    $question->attachVotes($user->getPHID());
    $object_phids = array($user->getPHID(), $question->getAuthorPHID());

    $answers = $question->getAnswers();
    $comments = $question->getComments();
    foreach ($comments as $comment) {
      $object_phids[] = $comment->getAuthorPHID();
    }

    foreach ($answers as $answer) {
      $object_phids[] = $answer->getAuthorPHID();

      $comments = $answer->getComments();
      foreach ($comments as $comment) {
        $object_phids[] = $comment->getAuthorPHID();
      }
    }

    $object_phids = array_merge($object_phids);

    $this->loadHandles($object_phids);
    $handles = $this->getLoadedHandles();

    $question_xactions = $this->buildQuestionTransactions($question);

    $responses_panel = new PonderAnswerListView();
    $responses_panel
      ->setQuestion($question)
      ->setHandles($handles)
      ->setUser($user)
      ->setAnswers($answers);

    $answer_add_panel = new PonderAddAnswerView();
    $answer_add_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setActionURI("/ponder/answer/add/");

    $header = id(new PhabricatorHeaderView())
      ->setHeader($question->getTitle());

    $actions = $this->buildActionListView($question);
    $properties = $this->buildPropertyListView($question);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('Q'.$this->questionID)
          ->setHref('/Q'.$this->questionID));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $question_xactions,
        $responses_panel,
        $answer_add_panel
      ),
      array(
        'device' => true,
        'title' => 'Q'.$question->getID().' '.$question->getTitle(),
        'dust' => true,
      ));
  }

  private function buildActionListView(PonderQuestion $question) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $question->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $question,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($request->getUser())
      ->setObject($question)
      ->setObjectURI($request->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Question'))
        ->setHref($this->getApplicationURI("/question/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($question->getStatus() == PonderQuestionStatus::STATUS_OPEN) {
      $name = pht("Close Question");
      $icon = "delete";
      $href = "close";
    } else {
      $name = pht("Reopen Question");
      $icon = "enable";
      $href = "open";
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($name)
        ->setIcon($icon)
        ->setRenderAsForm($can_edit)
        ->setWorkflow(!$can_edit)
        ->setDisabled(!$can_edit)
        ->setHref($this->getApplicationURI("/question/{$href}/{$id}/")));

    return $view;
  }

  private function buildPropertyListView(
    PonderQuestion $question) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($question);

    $view->addProperty(
      pht('Status'),
      PonderQuestionStatus::getQuestionStatusFullName($question->getStatus()));

    $view->addProperty(
      pht('Author'),
      $this->getHandle($question->getAuthorPHID())->renderLink());

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($question->getDateCreated(), $viewer));

    $view->invokeWillRenderEvent();

    $view->addTextContent(
      PhabricatorMarkupEngine::renderOneObject(
        $question,
        $question->getMarkupField(),
        $viewer));


    return $view;
  }

  private function buildQuestionTransactions(PonderQuestion $question) {
    $viewer = $this->getRequest()->getUser();

    $xactions = id(new PonderQuestionTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($question->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    // TODO: Add comment form.

    return $timeline;
  }

}
