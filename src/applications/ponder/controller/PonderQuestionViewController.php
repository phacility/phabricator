<?php

final class PonderQuestionViewController extends PonderController {

  private $questionID;

  public function willProcessRequest(array $data) {
    $this->questionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $question = id(new PonderQuestionQuery())
      ->setViewer($user)
      ->withIDs(array($this->questionID))
      ->needAnswers(true)
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $question->attachVotes($user->getPHID());

    $question_xactions = $this->buildQuestionTransactions($question);
    $answers = $this->buildAnswers($question->getAnswers());

    $authors = mpull($question->getAnswers(), null, 'getAuthorPHID');
    if (isset($authors[$user->getPHID()])) {
      // TODO: Make this pretty
      $answer_add_panel = pht(
        'You have already answered this question.');
    } else {
      $answer_add_panel = new PonderAddAnswerView();
      $answer_add_panel
        ->setQuestion($question)
        ->setUser($user)
        ->setActionURI("/ponder/answer/add/");
    }

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
        $answers,
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

    $this->loadHandles(array($question->getAuthorPHID()));

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
    $id = $question->getID();

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

    $add_comment = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setShowPreview(false)
      ->setAction($this->getApplicationURI("/question/comment/{$id}/"))
      ->setSubmitButtonName(pht('Comment'));

    return array(
      $timeline,
      $add_comment,
    );
  }

  private function buildAnswers(array $answers) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $out = array();

    $phids = mpull($answers, 'getAuthorPHID');
    $this->loadHandles($phids);

    $xactions = id(new PonderAnswerTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(mpull($answers, 'getPHID'))
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

    $xaction_groups = mgroup($xactions, 'getObjectPHID');

    foreach ($answers as $answer) {
      $author_phid = $answer->getAuthorPHID();
      $xactions = idx($xaction_groups, $answer->getPHID(), array());
      $id = $answer->getID();

      $out[] = phutil_tag('br');
      $out[] = phutil_tag('br');
      $out[] = id(new PhabricatorHeaderView())
        ->setHeader($this->getHandle($author_phid)->getFullName())
        ->setImage($this->getHandle($author_phid)->getImageURI());

      $out[] = $this->buildAnswerActions($answer);
      $out[] = $this->buildAnswerProperties($answer);

      $out[] = id(new PhabricatorApplicationTransactionView())
        ->setUser($viewer)
        ->setTransactions($xactions)
        ->setMarkupEngine($engine);

      $out[] = id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($viewer)
        ->setShowPreview(false)
        ->setAction($this->getApplicationURI("/answer/comment/{$id}/"))
        ->setSubmitButtonName(pht('Comment'));
    }

    $out[] = phutil_tag('br');
    $out[] = phutil_tag('br');

    return $out;
  }

  private function buildAnswerActions(PonderAnswer $answer) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $answer->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $answer,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($request->getUser())
      ->setObject($answer)
      ->setObjectURI($request->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Answer'))
        ->setHref($this->getApplicationURI("/answer/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $view;
  }

  private function buildAnswerProperties(PonderAnswer $answer) {
    $viewer = $this->getRequest()->getUser();
    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($answer);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($answer->getDateCreated(), $viewer));

    $view->invokeWillRenderEvent();

    $view->addTextContent(
      phutil_tag(
        'div',
        array(
          'class' => 'phabricator-remarkup',
        ),
        PhabricatorMarkupEngine::renderOneObject(
          $answer,
          $answer->getMarkupField(),
          $viewer)));

    return $view;
  }

}
