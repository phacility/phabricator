<?php

final class PhamePostEditController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $id = $request->getURIData('id');

    if ($id) {
      $post = id(new PhamePostQuery())
        ->setViewer($user)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }

      $cancel_uri = $this->getApplicationURI('/post/view/'.$id.'/');
      $submit_button = pht('Save Changes');
      $page_title = pht('Edit Post');

      $v_projects = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $post->getPHID(),
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
      $v_projects = array_reverse($v_projects);
    } else {
      $blog = id(new PhameBlogQuery())
        ->setViewer($user)
        ->withIDs(array($request->getInt('blog')))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_JOIN,
          ))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }
      $v_projects = array();

      $post = PhamePost::initializePost($user, $blog);
      $cancel_uri = $this->getApplicationURI('/blog/view/'.$blog->getID().'/');

      $submit_button = pht('Save Draft');
      $page_title    = pht('Create Post');
    }

    $title           = $post->getTitle();
    $phame_title     = $post->getPhameTitle();
    $body            = $post->getBody();
    $comments_widget = $post->getCommentsWidget();

    $e_title       = true;
    $e_phame_title = true;
    $validation_exception = null;
    if ($request->isFormPost()) {
      $title           = $request->getStr('title');
      $phame_title     = $request->getStr('phame_title');
      $phame_title     = PhabricatorSlug::normalize($phame_title);
      $body            = $request->getStr('body');
      $comments_widget = $request->getStr('comments_widget');
      $v_projects      = $request->getArr('projects');

      $xactions = array(
        id(new PhamePostTransaction())
          ->setTransactionType(PhamePostTransaction::TYPE_TITLE)
          ->setNewValue($title),
        id(new PhamePostTransaction())
          ->setTransactionType(PhamePostTransaction::TYPE_PHAME_TITLE)
          ->setNewValue($phame_title),
        id(new PhamePostTransaction())
          ->setTransactionType(PhamePostTransaction::TYPE_BODY)
          ->setNewValue($body),
        id(new PhamePostTransaction())
          ->setTransactionType(PhamePostTransaction::TYPE_COMMENTS_WIDGET)
          ->setNewValue($comments_widget),
      );

      $proj_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
      $xactions[] = id(new PhamePostTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $proj_edge_type)
        ->setNewValue(array('=' => array_fuse($v_projects)));

      $editor = id(new PhamePostEditor())
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($post, $xactions);

        $uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');
        return id(new AphrontRedirectResponse())->setURI($uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
        $e_title = $validation_exception->getShortMessage(
          PhamePostTransaction::TYPE_TITLE);
        $e_phame_title = $validation_exception->getShortMessage(
          PhamePostTransaction::TYPE_PHAME_TITLE);
      }
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(array($post->getBlogPHID()))
      ->executeOne();

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('blog', $request->getInt('blog'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Blog'))
          ->setValue($handle->renderLink()))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Title'))
        ->setName('title')
        ->setValue($title)
        ->setID('post-title')
        ->setError($e_title))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Phame Title'))
        ->setName('phame_title')
        ->setValue(rtrim($phame_title, '/'))
        ->setID('post-phame-title')
        ->setCaption(pht('Up to 64 alphanumeric characters '.
                     'with underscores for spaces. '.
                     'Formatting is enforced.'))
        ->setError($e_phame_title))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setLabel(pht('Body'))
        ->setName('body')
        ->setValue($body)
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setID('post-body')
        ->setUser($user)
        ->setDisableMacros(true))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Projects'))
          ->setName('projects')
          ->setValue($v_projects)
          ->setDatasource(new PhabricatorProjectDatasource()))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Comments Widget'))
        ->setName('comments_widget')
        ->setvalue($comments_widget)
        ->setOptions($post->getCommentsWidgetOptionsForSelect()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button));

    $loading = phutil_tag_div(
      'aphront-panel-preview-loading-text',
      pht('Loading preview...'));

    $preview_panel = phutil_tag_div('aphront-panel-preview', array(
      phutil_tag_div('phame-post-preview-header', pht('Post Preview')),
      phutil_tag('div', array('id' => 'post-preview'), $loading),
    ));

    Javelin::initBehavior(
      'phame-post-preview',
      array(
        'preview'     => 'post-preview',
        'body'        => 'post-body',
        'title'       => 'post-title',
        'phame_title' => 'post-phame-title',
        'uri'         => '/phame/post/preview/',
      ));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($page_title)
      ->setValidationException($validation_exception)
      ->setForm($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $page_title,
      $this->getApplicationURI('/post/view/'.$id.'/'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview_panel,
      ),
      array(
        'title' => $page_title,
      ));
  }

}
