<?php

final class PhameBlogEditController
  extends PhameBlogController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $blog = id(new PhameBlogQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }

      $submit_button = pht('Save Changes');
      $page_title = pht('Edit Blog');
      $cancel_uri = $this->getApplicationURI('blog/view/'.$blog->getID().'/');

      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $blog->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
      $v_cc = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $blog->getPHID());

    } else {
      $this->requireApplicationCapability(
        PhameBlogCreateCapability::CAPABILITY);

      $blog = PhameBlog::initializeNewBlog($viewer);

      $submit_button = pht('Create Blog');
      $page_title = pht('Create Blog');
      $cancel_uri = $this->getApplicationURI();
      $v_projects = array();
      $v_cc = array();
    }
    $name = $blog->getName();
    $description = $blog->getDescription();
    $custom_domain = $blog->getDomain();
    $skin = $blog->getSkin();
    $can_view = $blog->getViewPolicy();
    $can_edit = $blog->getEditPolicy();

    $e_name               = true;
    $e_custom_domain      = null;
    $e_view_policy        = null;
    $validation_exception = null;
    if ($request->isFormPost()) {
      $name = $request->getStr('name');
      $description = $request->getStr('description');
      $custom_domain = nonempty($request->getStr('custom_domain'), null);
      $skin = $request->getStr('skin');
      $can_view = $request->getStr('can_view');
      $can_edit = $request->getStr('can_edit');
      $v_projects = $request->getArr('projects');
      $v_cc = $request->getArr('cc');

      $xactions = array(
        id(new PhameBlogTransaction())
          ->setTransactionType(PhameBlogTransaction::TYPE_NAME)
          ->setNewValue($name),
        id(new PhameBlogTransaction())
          ->setTransactionType(PhameBlogTransaction::TYPE_DESCRIPTION)
          ->setNewValue($description),
        id(new PhameBlogTransaction())
          ->setTransactionType(PhameBlogTransaction::TYPE_DOMAIN)
          ->setNewValue($custom_domain),
        id(new PhameBlogTransaction())
          ->setTransactionType(PhameBlogTransaction::TYPE_SKIN)
          ->setNewValue($skin),
        id(new PhameBlogTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
          ->setNewValue($can_view),
        id(new PhameBlogTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
          ->setNewValue($can_edit),
        id(new PhameBlogTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(array('=' => $v_cc)),
      );

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new PhameBlogTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new PhameBlogEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($blog, $xactions);
        return id(new AphrontRedirectResponse())
          ->setURI($this->getApplicationURI('blog/view/'.$blog->getID().'/'));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $validation_exception->getShortMessage(
          PhameBlogTransaction::TYPE_NAME);
        $e_custom_domain = $validation_exception->getShortMessage(
          PhameBlogTransaction::TYPE_DOMAIN);
        $e_view_policy = $validation_exception->getShortMessage(
          PhabricatorTransactions::TYPE_VIEW_POLICY);
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($blog)
      ->execute();

    $skins = PhameSkinSpecification::loadAllSkinSpecifications();
    $skins = mpull($skins, 'getName');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Name'))
        ->setName('name')
        ->setValue($name)
        ->setID('blog-name')
        ->setError($e_name))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setLabel(pht('Description'))
          ->setName('description')
          ->setValue($description)
          ->setID('blog-description')
          ->setUser($viewer)
          ->setDisableMacros(true))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Subscribers'))
          ->setName('cc')
          ->setValue($v_cc)
          ->setUser($viewer)
          ->setDatasource(new PhabricatorMetaMTAMailableDatasource()))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($blog)
          ->setPolicies($policies)
          ->setError($e_view_policy)
          ->setValue($can_view)
          ->setName('can_view'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicyObject($blog)
          ->setPolicies($policies)
          ->setValue($can_edit)
          ->setName('can_edit'))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Custom Domain'))
        ->setName('custom_domain')
        ->setValue($custom_domain)
        ->setCaption(
          pht('Must include at least one dot (.), e.g. %s', 'blog.example.com'))
        ->setError($e_custom_domain))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Skin'))
        ->setName('skin')
        ->setValue($skin)
        ->setOptions($skins))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setValidationException($validation_exception)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Blogs'), $this->getApplicationURI('blog/'));
    $crumbs->addTextCrumb($page_title, $this->getApplicationURI('blog/new'));

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $form_box,
      ));
  }
}
