<?php

final class PonderQuestionViewController extends PonderController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needAnswers(true)
      ->needProjectPHIDs(true)
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $answers = $this->buildAnswers($question);

    $answer_add_panel = id(new PonderAddAnswerView())
      ->setQuestion($question)
      ->setUser($viewer)
      ->setActionURI('/ponder/answer/add/');

    $header = new PHUIHeaderView();
    $header->setHeader($question->getTitle());
    $header->setUser($viewer);
    $header->setPolicyObject($question);

    if ($question->getStatus() == PonderQuestionStatus::STATUS_OPEN) {
      $header->setStatus('fa-square-o', 'bluegrey', pht('Open'));
    } else {
      $text = PonderQuestionStatus::getQuestionStatusFullName(
        $question->getStatus());
      $icon = PonderQuestionStatus::getQuestionStatusIcon(
        $question->getStatus());
      $header->setStatus($icon, 'dark', $text);
    }

    $actions = $this->buildActionListView($question);
    $properties = $this->buildPropertyListView($question, $actions);
    $sidebar = $this->buildSidebar($question);

    $content_id = celerity_generate_unique_node_id();
    $timeline = $this->buildTransactionTimeline(
      $question,
      id(new PonderQuestionTransactionQuery())
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT)));
    $xactions = $timeline->getTransactions();

    $add_comment = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($question->getPHID())
      ->setShowPreview(false)
      ->setHeaderText(pht('Question Comment'))
      ->setAction($this->getApplicationURI("/question/comment/{$id}/"))
      ->setSubmitButtonName(pht('Comment'));

    $comment_view = phutil_tag(
      'div',
      array(
        'id' => $content_id,
        'style' => 'display: none;',
      ),
      array(
        $timeline,
        $add_comment,
      ));

    $footer = id(new PonderFooterView())
      ->setContentID($content_id)
      ->setCount(count($xactions));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties)
      ->appendChild($footer);

    if ($viewer->getPHID() == $question->getAuthorPHID()) {
      $status = $question->getStatus();
      $answers_list = $question->getAnswers();
      if ($answers_list && ($status == PonderQuestionStatus::STATUS_OPEN)) {
        $info_view = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->appendChild(
            pht(
              'If this question has been resolved, please consider closing
              the question and marking the answer as helpful.'));
        $object_box->setInfoView($info_view);
      }
    }

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addTextCrumb('Q'.$id, '/Q'.$id);

    $answer_wiki = null;
    if ($question->getAnswerWiki()) {
      $answer = phutil_tag_div('mlt mlb msr msl', $question->getAnswerWiki());
      $answer_wiki = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Answer Summary'))
        ->setColor(PHUIObjectBoxView::COLOR_BLUE)
        ->appendChild($answer);
    }

    $ponder_view = id(new PHUITwoColumnView())
      ->setMainColumn(array(
          $object_box,
          $comment_view,
          $answer_wiki,
          $answers,
          $answer_add_panel,
        ))
      ->setSideColumn($sidebar)
      ->addClass('ponder-question-view');

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $ponder_view,
      ),
      array(
        'title' => 'Q'.$question->getID().' '.$question->getTitle(),
        'pageObjects' => array_merge(
          array($question->getPHID()),
          mpull($question->getAnswers(), 'getPHID')),
      ));
  }

  private function buildActionListView(PonderQuestion $question) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $id = $question->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $question,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($question)
      ->setObjectURI($request->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Question'))
        ->setHref($this->getApplicationURI("/question/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($question->getStatus() == PonderQuestionStatus::STATUS_OPEN) {
      $name = pht('Close Question');
      $icon = 'fa-check-square-o';
    } else {
      $name = pht('Reopen Question');
      $icon = 'fa-square-o';
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($name)
        ->setIcon($icon)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setHref($this->getApplicationURI("/question/status/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-list')
        ->setName(pht('View History'))
        ->setHref($this->getApplicationURI("/question/history/{$id}/")));

    return $view;
  }

  private function buildPropertyListView(
    PonderQuestion $question,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($question)
      ->setActionList($actions);

    $view->addProperty(
      pht('Author'),
      $viewer->renderHandle($question->getAuthorPHID()));

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($question->getDateCreated(), $viewer));

    $view->invokeWillRenderEvent();

    $details = PhabricatorMarkupEngine::renderOneObject(
            $question,
            $question->getMarkupField(),
            $viewer);

    if ($details) {
      $view->addSectionHeader(
        pht('Details'),
        PHUIPropertyListView::ICON_SUMMARY);

      $view->addTextContent(
        array(
          phutil_tag(
            'div',
            array(
              'class' => 'phabricator-remarkup',
            ),
            $details),
        ));
    }

    return $view;
  }

  /**
   * This is fairly non-standard; building N timelines at once (N = number of
   * answers) is tricky business.
   *
   * TODO - re-factor this to ajax in one answer panel at a time in a more
   * standard fashion. This is necessary to scale this application.
   */
  private function buildAnswers(PonderQuestion $question) {
    $viewer = $this->getViewer();
    $answers = $question->getAnswers();

    $author_phids = mpull($answers, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($author_phids);
    $answers_sort = array_reverse(msort($answers, 'getVoteCount'));

    $view = array();
    foreach ($answers_sort as $answer) {
      $id = $answer->getID();
      $handle = $handles[$answer->getAuthorPHID()];

      $timeline = $this->buildTransactionTimeline(
        $answer,
        id(new PonderAnswerTransactionQuery())
        ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT)));
      $xactions = $timeline->getTransactions();


      $view[] = id(new PonderAnswerView())
        ->setUser($viewer)
        ->setAnswer($answer)
        ->setTransactions($xactions)
        ->setTimeline($timeline)
        ->setHandle($handle);

    }

    return $view;
  }

  private function buildSidebar(PonderQuestion $question) {
    $viewer = $this->getViewer();
    $status = $question->getStatus();
    $id = $question->getID();

    $questions = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withStatuses(array($status))
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_OR,
        $question->getProjectPHIDs())
      ->setLimit(10)
      ->execute();

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer)
      ->setNoDataString(pht('No similar questions found.'));

    foreach ($questions as $question) {
      if ($id == $question->getID()) {
        continue;
      }
      $item = new PHUIObjectItemView();
      $item->setObjectName('Q'.$question->getID());
      $item->setHeader($question->getTitle());
      $item->setHref('/Q'.$question->getID());
      $item->setObject($question);

      $item->addAttribute(
        pht('%d Answer(s)', $question->getAnswerCount()));

      $list->addItem($item);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Similar Questions'))
      ->setObjectList($list);

    return $box;
  }

}
