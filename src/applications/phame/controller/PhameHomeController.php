<?php

final class PhameHomeController extends PhamePostController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $blogs = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withStatuses(array(PhameBlog::STATUS_ACTIVE))
      ->needProfileImage(true)
      ->execute();

    $post_list = null;
    if ($blogs) {
      $blog_phids = mpull($blogs, 'getPHID');

      $pager = id(new AphrontCursorPagerView())
        ->readFromRequest($request);

      $posts = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withBlogPHIDs($blog_phids)
        ->withVisibility(PhameConstants::VISIBILITY_PUBLISHED)
        ->executeWithCursorPager($pager);

      if ($posts) {
        $post_list = id(new PhamePostListView())
          ->setPosts($posts)
          ->setViewer($viewer)
          ->showBlog(true);
      } else {
        $post_list = id(new PHUIBigInfoView())
          ->setIcon('fa-star')
          ->setTitle('No Visible Posts')
          ->setDescription(
            pht('There aren\'t any visible blog posts.'));
      }
    } else {
      $create_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Create a Blog'))
        ->setHref('/phame/blog/new/')
        ->setColor(PHUIButtonView::GREEN);

      $post_list = id(new PHUIBigInfoView())
        ->setIcon('fa-star')
        ->setTitle('Welcome to Phame')
        ->setDescription(
          pht('There aren\'t any visible blog posts.'))
        ->addAction($create_button);
    }

    $actions = $this->renderActions($viewer);
    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Actions'))
      ->setHref('#')
      ->setIconFont('fa-bars')
      ->addClass('phui-mobile-menu')
      ->setDropdownMenu($actions);

    $title = pht('Recent Posts');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->addActionLink($action_button);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb(
      pht('Recent Posts'),
      $this->getApplicationURI('post/'));

    $page = id(new PHUIDocumentViewPro())
      ->setHeader($header)
      ->appendChild($post_list);

    $blog_list = id(new PhameBlogListView())
      ->setBlogs($blogs)
      ->setViewer($viewer);

    $draft_list = null;
    if ($viewer->isLoggedIn() && $blogs) {
      $drafts = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withBloggerPHIDs(array($viewer->getPHID()))
        ->withBlogPHIDs(mpull($blogs, 'getPHID'))
        ->withVisibility(PhameConstants::VISIBILITY_DRAFT)
        ->setLimit(5)
        ->execute();

      $draft_list = id(new PhameDraftListView())
        ->setPosts($drafts)
        ->setBlogs($blogs)
        ->setViewer($viewer);
    }

    $phame_view = id(new PHUITwoColumnView())
      ->setMainColumn(array(
        $page,
      ))
      ->setSideColumn(array(
        $blog_list,
        $draft_list,
      ))
      ->setDisplay(PHUITwoColumnView::DISPLAY_LEFT)
      ->addClass('phame-home-view');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $phame_view,
      ));


  }

  private function renderActions($viewer) {
    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('post/query/draft/'))
        ->setName(pht('My Drafts')));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil-square-o')
        ->setHref($this->getApplicationURI('post/'))
        ->setName(pht('All Posts')));

    return $actions;
  }

  private function renderBlogs($viewer, $blogs) {}

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $can_create = $this->hasApplicationCapability(
      PhameBlogCreateCapability::CAPABILITY);

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blog'))
        ->setHref($this->getApplicationURI('/blog/new/'))
        ->setIcon('fa-plus-square')
        ->setDisabled(!$can_create)
        ->setWorkflow(!$can_create));

    return $crumbs;
  }

}
