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
      ->needViewerVotes(true)
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $question->attachVotes($user->getPHID());

    $question_xactions = $this->buildQuestionTransactions($question);
    $answers = $this->buildAnswers($question->getAnswers());

    $authors = mpull($question->getAnswers(), null, 'getAuthorPHID');
    if (isset($authors[$user->getPHID()])) {
      $answer_add_panel = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NODATA)
        ->appendChild(
          pht(
            'You have already answered this question. You can not answer '.
            'twice, but you can edit your existing answer.'));
    } else {
      $answer_add_panel = new PonderAddAnswerView();
      $answer_add_panel
        ->setQuestion($question)
        ->setUser($user)
        ->setActionURI("/ponder/answer/add/");
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($question->getTitle());

    $actions = $this->buildActionListView($question);
    $properties = $this->buildPropertyListView($question, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('Q'.$this->questionID)
          ->setHref('/Q'.$this->questionID));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $question_xactions,
        $answers,
        $answer_add_panel
      ),
      array(
        'device' => true,
        'title' => 'Q'.$question->getID().' '.$question->getTitle(),
        'pageObjects' => array($question->getPHID()),
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

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('transcript')
        ->setName(pht('View History'))
        ->setHref($this->getApplicationURI("/question/history/{$id}/")));

    return $view;
  }

  private function buildPropertyListView(
    PonderQuestion $question,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($question)
      ->setActionList($actions);

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

    $votable = id(new PonderVotableView())
      ->setPHID($question->getPHID())
      ->setURI($this->getApplicationURI('vote/'))
      ->setCount($question->getVoteCount())
      ->setVote($question->getUserVote());

    $view->addSectionHeader(pht('Question'));
    $view->addTextContent(
      array(
        $votable,
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          PhabricatorMarkupEngine::renderOneObject(
            $question,
            $question->getMarkupField(),
            $viewer)),
      ));


    return $view;
  }

  private function buildQuestionTransactions(PonderQuestion $question) {
    $viewer = $this->getRequest()->getUser();
    $id = $question->getID();

    $xactions = id(new PonderQuestionTransactionQuery())
      ->setViewer($viewer)
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
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
      ->setObjectPHID($question->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $add_comment = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($question->getPHID())
      ->setShowPreview(false)
      ->setAction($this->getApplicationURI("/question/comment/{$id}/"))
      ->setSubmitButtonName(pht('Comment'));

    $object_box = id(new PHUIObjectBoxView())
      ->setFlush(true)
      ->setHeaderText(pht('Question Comment'))
      ->appendChild($add_comment);

    return $this->wrapComments(
      count($xactions),
      array(
        $timeline,
        $object_box,
      ));
  }

  private function buildAnswers(array $answers) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $out = array();

    $phids = mpull($answers, 'getAuthorPHID');
    $this->loadHandles($phids);

    $xactions = id(new PonderAnswerTransactionQuery())
      ->setViewer($viewer)
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
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
      $out[] = id(new PhabricatorAnchorView())
        ->setAnchorName("A$id");
      $header = id(new PHUIHeaderView())
        ->setHeader($this->getHandle($author_phid)->getFullName());

      $actions = $this->buildAnswerActions($answer);
      $properties = $this->buildAnswerProperties($answer, $actions);

      $object_box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->addPropertyList($properties);

      $out[] = $object_box;
      $details = array();

      $details[] = id(new PhabricatorApplicationTransactionView())
        ->setUser($viewer)
        ->setObjectPHID($answer->getPHID())
        ->setTransactions($xactions)
        ->setMarkupEngine($engine);

      $form = id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($viewer)
        ->setObjectPHID($answer->getPHID())
        ->setShowPreview(false)
        ->setAction($this->getApplicationURI("/answer/comment/{$id}/"))
        ->setSubmitButtonName(pht('Comment'));

      $comment_box = id(new PHUIObjectBoxView())
        ->setFlush(true)
        ->setHeaderText(pht('Answer Comment'))
        ->appendChild($form);

      $details[] = $comment_box;

      $out[] = $this->wrapComments(
        count($xactions),
        $details);
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

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('transcript')
        ->setName(pht('View History'))
        ->setHref($this->getApplicationURI("/answer/history/{$id}/")));

    return $view;
  }

  private function buildAnswerProperties(
    PonderAnswer $answer,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($answer)
      ->setActionList($actions);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($answer->getDateCreated(), $viewer));

    $view->invokeWillRenderEvent();

    $votable = id(new PonderVotableView())
      ->setPHID($answer->getPHID())
      ->setURI($this->getApplicationURI('vote/'))
      ->setCount($answer->getVoteCount())
      ->setVote($answer->getUserVote());

    $view->addSectionHeader(pht('Answer'));
    $view->addTextContent(
      array(
        $votable,
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          PhabricatorMarkupEngine::renderOneObject(
            $answer,
            $answer->getMarkupField(),
            $viewer)),
      ));

    return $view;
  }

  private function wrapComments($n, $stuff) {
    if ($n == 0) {
      $text = pht('Add a Comment');
    } else {
      $text = pht('Show %s Comments', new PhutilNumber($n));
    }

    $show_id = celerity_generate_unique_node_id();
    $hide_id = celerity_generate_unique_node_id();

    Javelin::initBehavior('phabricator-reveal-content');
    require_celerity_resource('ponder-comment-table-css');

    $show = phutil_tag(
      'div',
      array(
        'id' => $show_id,
        'class' => 'ponder-show-comments',
      ),
      javelin_tag(
        'a',
        array(
          'href' => '#',
          'sigil' => 'reveal-content',
          'meta' => array(
            'showIDs' => array($hide_id),
            'hideIDs' => array($show_id),
          ),
        ),
        $text));

    $hide = phutil_tag(
      'div',
      array(
        'id' => $hide_id,
        'style' => 'display: none',
      ),
      $stuff);

    return array($show, $hide);
  }

}
