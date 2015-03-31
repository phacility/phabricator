<?php

final class PonderQuestionEditController extends PonderController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $question = id(new PonderQuestionQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
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
    } else {
      $question = id(new PonderQuestion())
        ->setStatus(PonderQuestionStatus::STATUS_OPEN)
        ->setAuthorPHID($user->getPHID())
        ->setVoteCount(0)
        ->setAnswerCount(0)
        ->setHeat(0.0);
      $v_projects = array();
    }

    $v_title = $question->getTitle();
    $v_content = $question->getContent();

    $errors = array();
    $e_title = true;
    if ($request->isFormPost()) {
      $v_title = $request->getStr('title');
      $v_content = $request->getStr('content');
      $v_projects = $request->getArr('projects');

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

        $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
        $xactions[] = id(new PonderQuestionTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
          ->setMetadataValue('edge:type', $proj_edge_type)
          ->setNewValue(array('=' => array_fuse($v_projects)));

        $editor = id(new PonderQuestionEditor())
          ->setActor($user)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($question, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/Q'.$question->getID());
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Question'))
          ->setName('title')
          ->setValue($v_title)
          ->setError($e_title))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($user)
          ->setName('content')
          ->setID('content')
          ->setValue($v_content)
          ->setLabel(pht('Description'))
          ->setUser($user));

    $form->appendControl(
      id(new AphrontFormTokenizerControl())
        ->setLabel(pht('Projects'))
        ->setName('projects')
        ->setValue($v_projects)
        ->setDatasource(new PhabricatorProjectDatasource()));

    $form ->appendChild(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($this->getApplicationURI())
        ->setValue(pht('Ask Away!')));

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Question Preview'))
      ->setControlID('content')
      ->setPreviewURI($this->getApplicationURI('preview/'));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Ask New Question'))
      ->setFormErrors($errors)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();

    $id = $question->getID();
    if ($id) {
      $crumbs->addTextCrumb("Q{$id}", "/Q{$id}");
      $crumbs->addTextCrumb(pht('Edit'));
    } else {
      $crumbs->addTextCrumb(pht('Ask Question'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview,
      ),
      array(
        'title'  => pht('Ask New Question'),
      ));
  }

}
