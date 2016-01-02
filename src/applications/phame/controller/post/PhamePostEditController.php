<?php

final class PhamePostEditController extends PhamePostController {

  private $blog;

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  public function getBlog() {
    return $this->blog;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $post = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }
      $blog_id = $post->getBlog()->getID();
    } else {
      $blog_id = head($request->getArr('blog'));
      if (!$blog_id) {
        $blog_id = $request->getStr('blog');
      }
    }

    $query = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ));

    if (ctype_digit($blog_id)) {
      $query->withIDs(array($blog_id));
    } else {
      $query->withPHIDs(array($blog_id));
    }

    $blog = $query->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $this->setBlog($blog);

    return id(new PhamePostEditEngine())
      ->setController($this)
      ->setBlog($blog)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $blog = $this->getBlog();
    if ($blog) {
      $crumbs->addTextCrumb(
        $blog->getName(),
        $blog->getViewURI());
    }

    return $crumbs;
  }


}
