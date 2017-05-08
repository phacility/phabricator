<?php

final class PhamePostEditEngine
  extends PhabricatorEditEngine {

  private $blog;

  const ENGINECONST = 'phame.post';

  public function getEngineName() {
    return pht('Blog Posts');
  }

  public function getSummaryHeader() {
    return pht('Configure Blog Post Forms');
  }

  public function getSummaryText() {
    return pht('Configure creation and editing blog posts in Phame.');
  }

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();

    if ($this->blog) {
      $blog = $this->blog;
    } else {
      $blog = PhameBlog::initializeNewBlog($viewer);
    }

    return PhamePost::initializePost($viewer, $blog);
  }

  protected function newObjectQuery() {
    return new PhamePostQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Post');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s', $object->getTitle());
  }

  protected function getObjectEditShortText($object) {
    return $object->getTitle();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Post');
  }

  protected function getObjectName() {
    return pht('Post');
  }

  protected function getObjectViewURI($object) {
    return $object->getViewURI();
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('post/edit/');
  }

  protected function buildCustomEditFields($object) {
    $blog_phid = $object->getBlog()->getPHID();

    return array(
      id(new PhabricatorHandlesEditField())
        ->setKey('blog')
        ->setLabel(pht('Blog'))
        ->setDescription(pht('Blog to publish this post to.'))
        ->setConduitDescription(
          pht('Choose a blog to create a post on (or move a post to).'))
        ->setConduitTypeDescription(pht('PHID of the blog.'))
        ->setAliases(array('blogPHID'))
        ->setTransactionType(PhamePostBlogTransaction::TRANSACTIONTYPE)
        ->setHandleParameterType(new AphrontPHIDListHTTPParameterType())
        ->setSingleValue($blog_phid)
        ->setIsReorderable(false)
        ->setIsDefaultable(false)
        ->setIsLockable(false)
        ->setIsLocked(true),
      id(new PhabricatorTextEditField())
        ->setKey('title')
        ->setLabel(pht('Title'))
        ->setDescription(pht('Post title.'))
        ->setConduitDescription(pht('Retitle the post.'))
        ->setConduitTypeDescription(pht('New post title.'))
        ->setTransactionType(PhamePostTitleTransaction::TRANSACTIONTYPE)
        ->setIsRequired(true)
        ->setValue($object->getTitle()),
      id(new PhabricatorTextEditField())
        ->setKey('subtitle')
        ->setLabel(pht('Subtitle'))
        ->setDescription(pht('Post subtitle.'))
        ->setConduitDescription(pht('Change the post subtitle.'))
        ->setConduitTypeDescription(pht('New post subtitle.'))
        ->setTransactionType(PhamePostSubtitleTransaction::TRANSACTIONTYPE)
        ->setValue($object->getSubtitle()),
      id(new PhabricatorSelectEditField())
        ->setKey('visibility')
        ->setLabel(pht('Visibility'))
        ->setDescription(pht('Post visibility.'))
        ->setConduitDescription(pht('Change post visibility.'))
        ->setConduitTypeDescription(pht('New post visibility constant.'))
        ->setTransactionType(PhamePostVisibilityTransaction::TRANSACTIONTYPE)
        ->setValue($object->getVisibility())
        ->setOptions(PhameConstants::getPhamePostStatusMap()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('body')
        ->setLabel(pht('Body'))
        ->setDescription(pht('Post body.'))
        ->setConduitDescription(pht('Change post body.'))
        ->setConduitTypeDescription(pht('New post body.'))
        ->setTransactionType(PhamePostBodyTransaction::TRANSACTIONTYPE)
        ->setValue($object->getBody())
        ->setPreviewPanel(
          id(new PHUIRemarkupPreviewPanel())
            ->setHeader(pht('Blog Post'))
            ->setPreviewType(PHUIRemarkupPreviewPanel::DOCUMENT)),
    );
  }

}
