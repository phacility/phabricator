<?php

final class PhameBlogViewController extends PhameBlogController {

  private $blog;

  public function shouldAllowPublic() {
    return true;
  }

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

    $post_list = id(new PhamePostListView())
      ->setPosts($posts)
      ->setViewer($viewer)
      ->setNodata(pht('This blog has no visible posts.'));

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

    $description = null;
    if (strlen($blog->getDescription())) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($blog->getDescription()),
        'default',
        $viewer);
    } else {
      $description = phutil_tag('em', array(), pht('No description.'));
    }

    $about = id(new PhameDescriptionView())
      ->setTitle(pht('About %s', $blog->getName()))
      ->setDescription($description)
      ->setImage($blog->getProfileImageURI());

    return $this->newPage()
      ->setTitle($blog->getName())
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $page,
          $about,
      ));
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
        ->setName(pht('Manage Blog')));

    return $actions;
  }

}
