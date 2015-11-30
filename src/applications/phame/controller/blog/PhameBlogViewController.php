<?php

final class PhameBlogViewController extends PhameBlogController {

  private $blog;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $blog = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfileImage(true)
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }
    $this->blog = $blog;

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = id(new PhamePostQuery())
      ->setViewer($viewer)
      ->withBlogPHIDs(array($blog->getPHID()))
      ->executeWithCursorPager($pager);

    if ($blog->isArchived()) {
      $header_icon = 'fa-ban';
      $header_name = pht('Archived');
      $header_color = 'dark';
    } else {
      $header_icon = 'fa-check';
      $header_name = pht('Active');
      $header_color = 'bluegrey';
    }

    $actions = $this->renderActions($blog, $viewer);
    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setHref('#')
      ->setIconFont('fa-bars')
      ->addClass('phui-mobile-menu')
      ->setDropdownMenu($actions);

    $header = id(new PHUIHeaderView())
      ->setHeader($blog->getName())
      ->setUser($viewer)
      ->setPolicyObject($blog)
      ->setStatus($header_icon, $header_color, $header_name)
      ->addActionLink($action_button);

    $post_list = $this->renderPostList(
      $posts,
      $viewer,
      pht('This blog has no visible posts.'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(
      pht('Blogs'),
      $this->getApplicationURI('blog/'));
    $crumbs->addTextCrumb(
      $blog->getName());

    $page = id(new PHUIDocumentViewPro())
      ->setHeader($header)
      ->appendChild($post_list);

    $description = $this->renderDescription($blog, $viewer);

    return $this->newPage()
      ->setTitle($blog->getName())
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $page,
          $description,
      ));
  }

  private function renderDescription(
    PhameBlog $blog,
    PhabricatorUser $viewer) {

    require_celerity_resource('phame-css');

    if (strlen($blog->getDescription())) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($blog->getDescription()),
        'default',
        $viewer);
    } else {
      $description = phutil_tag('em', array(), pht('No description.'));
    }

    $picture = $blog->getProfileImageURI();
    $description = phutil_tag_div(
      'phame-blog-description-content', $description);

    $image = phutil_tag(
      'div',
      array(
        'class' => 'phame-blog-description-image',
        'style' => 'background-image: url('.$picture.');',
      ));

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phame-blog-description-name',
      ),
      pht('About %s', $blog->getName()));

    $view = phutil_tag(
      'div',
      array(
        'class' => 'phame-blog-description',
      ),
      array(
        $image,
        $header,
        $description,
      ));

    return $view;
  }

  protected function renderPostList(
    array $posts,
    PhabricatorUser $viewer,
    $nodata) {
    assert_instances_of($posts, 'PhamePost');

    $handle_phids = array();
    foreach ($posts as $post) {
      $handle_phids[] = $post->getBloggerPHID();
      if ($post->getBlog()) {
        $handle_phids[] = $post->getBlog()->getPHID();
      }
    }
    $handles = $viewer->loadHandles($handle_phids);

    $list = array();
    foreach ($posts as $post) {
      $blogger = $handles[$post->getBloggerPHID()]->renderLink();
      $blogger_uri = $handles[$post->getBloggerPHID()]->getURI();
      $blogger_image = $handles[$post->getBloggerPHID()]->getImageURI();

      $phame_post = null;
      if ($post->getBody()) {
        $phame_post = PhabricatorMarkupEngine::summarize($post->getBody());
        $phame_post = new PHUIRemarkupView($viewer, $phame_post);
      } else {
        $phame_post = phutil_tag('em', array(), pht('Empty Post'));
      }

      $blogger = phutil_tag('strong', array(), $blogger);
      $date = phabricator_datetime($post->getDatePublished(), $viewer);
      $subtitle = pht('Written by %s on %s.', $blogger, $date);

      $item = id(new PHUIDocumentSummaryView())
        ->setTitle($post->getTitle())
        ->setHref($this->getApplicationURI('/post/view/'.$post->getID().'/'))
        ->setSubtitle($subtitle)
        ->setImage($blogger_image)
        ->setImageHref($blogger_uri)
        ->setSummary($phame_post);

      $list[] = $item;
    }

    if (empty($list)) {
      $list = id(new PHUIInfoView())
        ->appendChild($nodata);
    }

    return $list;
  }

  private function renderActions(PhameBlog $blog, PhabricatorUser $viewer) {
    $actions = id(new PhabricatorActionListView())
      ->setObject($blog)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $blog,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-plus')
        ->setHref($this->getApplicationURI('post/edit/?blog='.$blog->getID()))
        ->setName(pht('Write Post'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setUser($viewer)
        ->setIcon('fa-globe')
        ->setHref($blog->getLiveURI())
        ->setName(pht('View Live')));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('blog/manage/'.$blog->getID().'/'))
        ->setName(pht('Manage Blog'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

}
