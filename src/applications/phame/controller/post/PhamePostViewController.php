<?php

final class PhamePostViewController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$post) {
      return new Aphront404Response();
    }

    $nav = $this->renderSideNavFilterView();

    $actions = $this->renderActions($post, $user);
    $properties = $this->renderProperties($post, $user, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $post->getTitle(),
      $this->getApplicationURI('post/view/'.$post->getID().'/'));

    $nav->appendChild($crumbs);

    $header = id(new PHUIHeaderView())
        ->setHeader($post->getTitle())
        ->setUser($user)
        ->setPolicyObject($post);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    if ($post->isDraft()) {
      $object_box->appendChild(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
          ->setTitle(pht('Draft Post'))
          ->appendChild(
            pht('Only you can see this draft until you publish it. '.
                'Use "Preview / Publish" to publish this post.')));
    }

    if (!$post->getBlog()) {
      $object_box->appendChild(
        id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
          ->setTitle(pht('Not On A Blog'))
          ->appendChild(
            pht('This post is not associated with a blog (the blog may have '.
                'been deleted). Use "Move Post" to move it to a new blog.')));
    }

    $nav->appendChild(
      array(
        $object_box,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $post->getTitle(),
      ));
  }

  private function renderActions(
    PhamePost $post,
    PhabricatorUser $user) {

    $actions = id(new PhabricatorActionListView())
      ->setObject($post)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($user);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $post,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $post->getID();

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('post/edit/'.$id.'/'))
        ->setName(pht('Edit Post'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-arrows')
        ->setHref($this->getApplicationURI('post/move/'.$id.'/'))
        ->setName(pht('Move Post'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($post->isDraft()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-eye')
          ->setHref($this->getApplicationURI('post/publish/'.$id.'/'))
          ->setName(pht('Preview / Publish')));
    } else {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('fa-eye-slash')
          ->setHref($this->getApplicationURI('post/unpublish/'.$id.'/'))
          ->setName(pht('Unpublish'))
          ->setWorkflow(true));
    }

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setHref($this->getApplicationURI('post/delete/'.$id.'/'))
        ->setName(pht('Delete Post'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $blog = $post->getBlog();
    $can_view_live = $blog && !$post->isDraft();

    if ($can_view_live) {
      $live_uri = $blog->getLiveURI($post);
    } else {
      $live_uri = 'post/notlive/'.$post->getID().'/';
      $live_uri = $this->getApplicationURI($live_uri);
    }

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setUser($user)
        ->setIcon('fa-globe')
        ->setHref($live_uri)
        ->setName(pht('View Live'))
        ->setDisabled(!$can_view_live)
        ->setWorkflow(!$can_view_live));

    return $actions;
  }

  private function renderProperties(
    PhamePost $post,
    PhabricatorUser $user,
    PhabricatorActionListView $actions) {

    $properties = id(new PHUIPropertyListView())
      ->setUser($user)
      ->setObject($post)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Blog'),
      $user->renderHandle($post->getBlogPHID()));

    $properties->addProperty(
      pht('Blogger'),
      $user->renderHandle($post->getBloggerPHID()));

    $properties->addProperty(
      pht('Published'),
      $post->isDraft()
        ? pht('Draft')
        : phabricator_datetime($post->getDatePublished(), $user));

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user)
      ->addObject($post, PhamePost::MARKUP_FIELD_BODY)
      ->process();

    $properties->invokeWillRenderEvent();

    $properties->addTextContent(
      phutil_tag(
         'div',
        array(
          'class' => 'phabricator-remarkup',
        ),
        $engine->getOutput($post, PhamePost::MARKUP_FIELD_BODY)));

    return $properties;
  }

}
