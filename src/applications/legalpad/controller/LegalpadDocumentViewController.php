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

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionView($document);
    $properties = $this->buildPropertyView($document, $engine);

    $comment_form_id = celerity_generate_unique_node_id();

    $xaction_view = id(new LegalpadTransactionView())
      ->setUser($this->getRequest()->getUser())
      ->setObjectPHID($document->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $add_comment = $this->buildAddCommentView($document, $comment_form_id);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNav());
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('L'.$document->getID())
        ->setHref($this->getApplicationURI('view/'.$document->getID())));

    $content = array(
      $crumbs,
      $header,
      $actions,
      $properties,
      $this->buildDocument($engine, $document_body),
      $xaction_view,
      $add_comment,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
        'pageObjects' => array($document->getPHID()),
      ));
  }

  private function buildDocument(
    PhabricatorMarkupEngine
    $engine, LegalpadDocumentBody $body) {

    require_celerity_resource('legalpad-documentbody-css');

    return phutil_tag(
      'div',
      array(
        'class' => 'legalpad-documentbody'
      ),
      $engine->getOutput($body, LegalpadDocumentBody::MARKUP_FIELD_TEXT));

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

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Document'))
        ->setHref($this->getApplicationURI('/edit/'.$document->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

  private function buildPropertyView(
    LegalpadDocument $document,
    PhabricatorMarkupEngine $engine) {

    $user = $this->getRequest()->getUser();

    $properties = id(new PhabricatorPropertyListView())
      ->setUser($user)
      ->setObject($document);

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

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $document);

    $properties->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

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

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $button_name = $is_serious
      ? pht('Add Comment')
      : pht('Commence Filibuster');

    $form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($user)
      ->setObjectPHID($document->getPHID())
      ->setFormID($comment_form_id)
      ->setDraft($draft)
      ->setSubmitButtonName($button_name)
      ->setAction($this->getApplicationURI('/comment/'.$document->getID().'/'))
      ->setRequestURI($this->getRequest()->getRequestURI());

    return array(
      $header,
      $form,
    );
  }

}
