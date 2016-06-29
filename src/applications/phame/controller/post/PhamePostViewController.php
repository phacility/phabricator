<?php

final class PhamePostViewController
  extends PhameLiveController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->setupLiveEnvironment();
    if ($response) {
      return $response;
    }

    $viewer = $request->getViewer();
    $moved = $request->getStr('moved');

    $post = $this->getPost();
    $blog = $this->getBlog();

    $is_live = $this->getIsLive();
    $is_external = $this->getIsExternal();

    $header = id(new PHUIHeaderView())
      ->setHeader($post->getTitle())
      ->setUser($viewer);

    if (!$is_external) {
      $actions = $this->renderActions($post);
      $header->setPolicyObject($post);
      $header->setActionList($actions);
    }

    $document = id(new PHUIDocumentViewPro())
      ->setHeader($header);

    if ($moved) {
      $document->appendChild(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
          ->appendChild(pht('Post moved successfully.')));
    }

    if ($post->isDraft()) {
      $document->appendChild(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
          ->setTitle(pht('Draft Post'))
          ->appendChild(
            pht('Only you can see this draft until you publish it. '.
                'Use "Publish" to publish this post.')));
    }

    if ($post->isArchived()) {
      $document->appendChild(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_ERROR)
          ->setTitle(pht('Archived Post'))
          ->appendChild(
            pht('Only you can see this archived post until you publish it. '.
                'Use "Publish" to publish this post.')));
    }

    if (!$post->getBlog()) {
      $document->appendChild(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setTitle(pht('Not On A Blog'))
          ->appendChild(
            pht('This post is not associated with a blog (the blog may have '.
                'been deleted). Use "Move Post" to move it to a new blog.')));
    }

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->addObject($post, PhamePost::MARKUP_FIELD_BODY)
      ->process();

    $document->appendChild(
      phutil_tag(
         'div',
        array(
          'class' => 'phabricator-remarkup',
        ),
        $engine->getOutput($post, PhamePost::MARKUP_FIELD_BODY)));

    $blogger = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($post->getBloggerPHID()))
      ->needProfileImage(true)
      ->executeOne();
    $blogger_profile = $blogger->loadUserProfile();


    $author_uri = '/p/'.$blogger->getUsername().'/';
    $author_uri = PhabricatorEnv::getURI($author_uri);

    $author = phutil_tag(
      'a',
      array(
        'href' => $author_uri,
      ),
      $blogger->getUsername());

    $date = phabricator_datetime($post->getDatePublished(), $viewer);
    if ($post->isDraft()) {
      $subtitle = pht('Unpublished draft by %s.', $author);
    } else if ($post->isArchived()) {
      $subtitle = pht('Archived post by %s.', $author);
    } else {
      $subtitle = pht('Written by %s on %s.', $author, $date);
    }

    $user_icon = $blogger_profile->getIcon();
    $user_icon = PhabricatorPeopleIconSet::getIconIcon($user_icon);
    $user_icon = id(new PHUIIconView())->setIcon($user_icon);

    $about = id(new PhameDescriptionView())
      ->setTitle($subtitle)
      ->setDescription(
        array(
          $user_icon,
          ' ',
          $blogger_profile->getTitle(),
        ))
      ->setImage($blogger->getProfileImageURI())
      ->setImageHref($author_uri);

    $timeline = $this->buildTransactionTimeline(
      $post,
      id(new PhamePostTransactionQuery())
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT)));
    $timeline = phutil_tag_div('phui-document-view-pro-box', $timeline);

    if ($is_external) {
      $add_comment = null;
    } else {
      $add_comment = $this->buildCommentForm($post);
      $add_comment = phutil_tag_div('mlb mlt', $add_comment);
    }

    list($prev, $next) = $this->loadAdjacentPosts($post);

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($post);

    $next_view = new PhameNextPostView();
    if ($next) {
      $next_view->setNext($next->getTitle(), $next->getLiveURI());
    }
    if ($prev) {
      $next_view->setPrevious($prev->getTitle(), $prev->getLiveURI());
    }

    $document->setFoot($next_view);
    $crumbs = $this->buildApplicationCrumbs();
    $properties = phutil_tag_div('phui-document-view-pro-box', $properties);

    $page = $this->newPage()
      ->setTitle($post->getTitle())
      ->setPageObjectPHIDs(array($post->getPHID()))
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $document,
          $about,
          $properties,
          $timeline,
          $add_comment,
      ));

    if ($is_live) {
      $page
        ->setShowChrome(false)
        ->setShowFooter(false);
    }

    return $page;
  }

  private function renderActions(PhamePost $post) {
    $viewer = $this->getViewer();

    $actions = id(new PhabricatorActionListView())
      ->setObject($post)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $post,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $post->getID();

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('post/edit/'.$id.'/'))
        ->setName(pht('Edit Post'))
        ->setDisabled(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-arrows')
        ->setHref($this->getApplicationURI('post/move/'.$id.'/'))
        ->setName(pht('Move Post'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-history')
        ->setHref($this->getApplicationURI('post/history/'.$id.'/'))
        ->setName(pht('View History')));

    if ($post->isDraft()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-eye')
          ->setHref($this->getApplicationURI('post/publish/'.$id.'/'))
          ->setName(pht('Publish'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI('post/archive/'.$id.'/'))
          ->setName(pht('Archive'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else if ($post->isArchived()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-eye')
          ->setHref($this->getApplicationURI('post/publish/'.$id.'/'))
          ->setName(pht('Publish'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-eye-slash')
          ->setHref($this->getApplicationURI('post/unpublish/'.$id.'/'))
          ->setName(pht('Unpublish'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-ban')
          ->setHref($this->getApplicationURI('post/archive/'.$id.'/'))
          ->setName(pht('Archive'))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));
    }

    if ($post->isDraft()) {
      $live_name = pht('Preview');
    } else {
      $live_name = pht('View Live');
    }

    if (!$post->isArchived()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setUser($viewer)
          ->setIcon('fa-globe')
          ->setHref($post->getLiveURI())
          ->setName($live_name));
    }

    return $actions;
  }

  private function buildCommentForm(PhamePost $post) {
    $viewer = $this->getViewer();

    $draft = PhabricatorDraft::newFromUserAndKey(
      $viewer, $post->getPHID());

    $box = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($post->getPHID())
      ->setDraft($draft)
      ->setHeaderText(pht('Add Comment'))
      ->setAction($this->getApplicationURI('post/comment/'.$post->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));

    return phutil_tag_div('phui-document-view-pro-box', $box);
  }

  private function loadAdjacentPosts(PhamePost $post) {
    $viewer = $this->getViewer();

    $query = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withVisibility(array(PhameConstants::VISIBILITY_PUBLISHED))
      ->withBlogPHIDs(array($post->getBlog()->getPHID()))
      ->setLimit(1);

    $prev = id(clone $query)
      ->setAfterID($post->getID())
      ->execute();

    $next = id(clone $query)
      ->setBeforeID($post->getID())
      ->execute();

    return array(head($prev), head($next));
  }

}
