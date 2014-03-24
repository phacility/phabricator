<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentViewController extends LegalpadController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new LegalpadDocumentQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needDocumentBodies(true)
      ->needContributors(true)
      ->executeOne();

    if (!$document) {
      return new Aphront404Response();
    }

    $xactions = id(new LegalpadTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($document->getPHID()))
      ->execute();

    $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $document->getPHID());

    $document_body = $document->getDocumentBody();
    $phids = array();
    $phids[] = $document_body->getCreatorPHID();
    foreach ($subscribers as $subscriber) {
      $phids[] = $subscriber;
    }
    foreach ($document->getContributors() as $contributor) {
      $phids[] = $contributor;
    }
    $this->loadHandles($phids);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    $engine->addObject(
      $document_body,
      LegalpadDocumentBody::MARKUP_FIELD_TEXT);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $title = $document_body->getTitle();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($user)
      ->setPolicyObject($document);

    $actions = $this->buildActionView($document);
    $properties = $this->buildPropertyView($document, $engine, $actions);

    $comment_form_id = celerity_generate_unique_node_id();

    $xaction_view = id(new LegalpadTransactionView())
      ->setUser($this->getRequest()->getUser())
      ->setObjectPHID($document->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $add_comment = $this->buildAddCommentView($document, $comment_form_id);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $crumbs->setActionList($actions);
    $crumbs->addTextCrumb(
      $document->getMonogram(),
      $this->getApplicationURI('view/'.$document->getID()));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties)
      ->addPropertyList($this->buildDocument($engine, $document_body));

    $content = array(
      $crumbs,
      $object_box,
      $xaction_view,
      $add_comment,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
        'pageObjects' => array($document->getPHID()),
      ));
  }

  private function buildDocument(
    PhabricatorMarkupEngine
    $engine, LegalpadDocumentBody $body) {

    $view = new PHUIPropertyListView();
    $view->addClass('legalpad');
    $view->addSectionHeader(pht('Document'));
    $view->addTextContent(
      $engine->getOutput($body, LegalpadDocumentBody::MARKUP_FIELD_TEXT));

    return $view;

  }

  private function buildActionView(LegalpadDocument $document) {
    $user = $this->getRequest()->getUser();

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($document);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $document,
      PhabricatorPolicyCapability::CAN_EDIT);

    $doc_id = $document->getID();

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Document'))
        ->setHref($this->getApplicationURI('/edit/'.$doc_id.'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
      ->setIcon('like')
      ->setName(pht('Sign Document'))
      ->setHref('/'.$document->getMonogram()));

    $actions->addAction(
      id(new PhabricatorActionView())
      ->setIcon('transcript')
      ->setName(pht('View Signatures'))
      ->setHref($this->getApplicationURI('/signatures/'.$doc_id.'/')));

    return $actions;
  }

  private function buildPropertyView(
    LegalpadDocument $document,
    PhabricatorMarkupEngine $engine,
    PhabricatorActionListView $actions) {

    $user = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setObject($document)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Last Updated'),
      phabricator_datetime($document->getDateModified(), $user));

    $properties->addProperty(
      pht('Updated By'),
      $this->getHandle(
        $document->getDocumentBody()->getCreatorPHID())->renderLink());

    $properties->addProperty(
      pht('Versions'),
      $document->getVersions());

    $contributor_view = array();
    foreach ($document->getContributors() as $contributor) {
      $contributor_view[] = $this->getHandle($contributor)->renderLink();
    }
    $contributor_view = phutil_implode_html(', ', $contributor_view);
    $properties->addProperty(
      pht('Contributors'),
      $contributor_view);

    $properties->invokeWillRenderEvent();

    return $properties;
  }

  private function buildAddCommentView(
    LegalpadDocument $document,
    $comment_form_id) {
    $user = $this->getRequest()->getUser();

    $draft = PhabricatorDraft::newFromUserAndKey($user, $document->getPHID());

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $title = $is_serious
      ? pht('Add Comment')
      : pht('Debate Legislation');

    $button_name = $is_serious
      ? pht('Add Comment')
      : pht('Commence Filibuster');

    $form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($user)
      ->setObjectPHID($document->getPHID())
      ->setFormID($comment_form_id)
      ->setHeaderText($title)
      ->setDraft($draft)
      ->setSubmitButtonName($button_name)
      ->setAction($this->getApplicationURI('/comment/'.$document->getID().'/'))
      ->setRequestURI($this->getRequest()->getRequestURI());

    return $form;

  }

}
