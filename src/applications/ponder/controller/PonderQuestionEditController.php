<?php

final class PonderQuestionEditController extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $question = id(new PonderQuestionQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$question) {
        return new Aphront404Response();
      }
      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $question->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
      $is_new = false;
    } else {
      $is_new = true;
      $question = PonderQuestion::initializeNewQuestion($viewer);
      $v_projects = array();
    }

    $v_title = $question->getTitle();
    $v_content = $question->getContent();
    $v_wiki = $question->getAnswerWiki();
    $v_view = $question->getViewPolicy();
    $v_space = $question->getSpacePHID();
    $v_status = $question->getStatus();


    $errors = array();
    $e_title = true;
    if ($request->isFormPost()) {
      $v_title = $request->getStr('title');
      $v_content = $request->getStr('content');
      $v_wiki = $request->getStr('answerWiki');
      $v_projects = $request->getArr('projects');
      $v_view = $request->getStr('viewPolicy');
      $v_space = $request->getStr('spacePHID');
      $v_status = $request->getStr('status');

      $len = phutil_utf8_strlen($v_title);
      if ($len < 1) {
        $errors[] = pht('Title must not be empty.');
        $e_title = pht('Required');
      } else if ($len > 255) {
        $errors[] = pht('Title is too long.');
        $e_title = pht('Too Long');
      }

      if (!$errors) {
        $template = id(new PonderQuestionTransaction());
        $xactions = array();

        $xactions[] = id(clone $template)
          ->setTransactionType(PonderQuestionTransaction::TYPE_TITLE)
          ->setNewValue($v_title);

        $xactions[] = id(clone $template)
          ->setTransactionType(PonderQuestionTransaction::TYPE_CONTENT)
          ->setNewValue($v_content);

        $xactions[] = id(clone $template)
          ->setTransactionType(PonderQuestionTransaction::TYPE_ANSWERWIKI)
          ->setNewValue($v_wiki);

        if (!$is_new) {
          $xactions[] = id(clone $template)
            ->setTransactionType(PonderQuestionTransaction::TYPE_STATUS)
            ->setNewValue($v_status);
        }

        $xactions[] = id(clone $template)
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($v_view);

        $xactions[] = id(clone $template)
          ->setTransactionType(PhabricatorTransactions::TYPE_SPACE)
          ->setNewValue($v_space);

        $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $xactions[] = id(new PonderQuestionTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $proj_edge_type)
          ->setNewValue(array('=' => array_fuse($v_projects)));

        $editor = id(new PonderQuestionEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($question, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/Q'.$question->getID());
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($question)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Question'))
          ->setName('title')
          ->setValue($v_title)
          ->setError($e_title))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('content')
          ->setID('content')
          ->setValue($v_content)
          ->setLabel(pht('Question Details'))
          ->setUser($viewer))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('answerWiki')
          ->setID('answerWiki')
          ->setValue($v_wiki)
          ->setLabel(pht('Answer Summary'))
          ->setUser($viewer))
      ->appendControl(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($question)
          ->setSpacePHID($v_space)
          ->setPolicies($policies)
          ->setValue($v_view)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW));


    if (!$is_new) {
      $form->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Status'))
            ->setName('status')
            ->setValue($v_status)
            ->setOptions(PonderQuestionStatus::getQuestionStatusMap()));
    }

    $form->appendControl(
      id(new AphrontFormTokenizerControl())
        ->setLabel(pht('Projects'))
        ->setName('projects')
        ->setValue($v_projects)
        ->setDatasource(new PhabricatorProjectDatasource()));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($this->getApplicationURI())
        ->setValue(pht('Submit')));

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Question Preview'))
      ->setControlID('content')
      ->setPreviewURI($this->getApplicationURI('preview/'));

    $answer_preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Answer Summary Preview'))
      ->setControlID('answerWiki')
      ->setPreviewURI($this->getApplicationURI('preview/'));

    $crumbs = $this->buildApplicationCrumbs();

    $id = $question->getID();
    if ($id) {
      $crumbs->addTextCrumb("Q{$id}", "/Q{$id}");
      $crumbs->addTextCrumb(pht('Edit'));
      $title = pht('Edit Question');
    } else {
      $crumbs->addTextCrumb(pht('Ask Question'));
      $title = pht('Ask New Question');
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview,
        $answer_preview,
      ),
      array(
        'title'  => $title,
      ));
  }

}
