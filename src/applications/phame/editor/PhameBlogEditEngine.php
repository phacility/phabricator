<?php

final class PhameBlogEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'phame.blog';

  public function getEngineName() {
    return pht('Blogs');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getSummaryHeader() {
    return pht('Configure Phame Blog Forms');
  }

  public function getSummaryText() {
    return pht('Configure how blogs in Phame are created and edited.');
  }

  protected function newEditableObject() {
    return PhameBlog::initializeNewBlog($this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new PhameBlogQuery())
      ->needProfileImage(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Blog');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Blog');
  }

  protected function getObjectName() {
    return pht('Blog');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getApplication()->getApplicationURI('blog/');
  }

  protected function getEditorURI() {
    return $this->getApplication()->getApplicationURI('blog/edit/');
  }

  protected function getObjectViewURI($object) {
    return $object->getManageURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      PhameBlogCreateCapability::CAPABILITY);
  }

  protected function buildCustomEditFields($object) {
    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setDescription(pht('Blog name.'))
        ->setConduitDescription(pht('Retitle the blog.'))
        ->setConduitTypeDescription(pht('New blog title.'))
        ->setTransactionType(PhameBlogTransaction::TYPE_NAME)
        ->setValue($object->getName()),
     id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setDescription(pht('Blog description.'))
        ->setConduitDescription(pht('Change the blog description.'))
        ->setConduitTypeDescription(pht('New blog description.'))
        ->setTransactionType(PhameBlogTransaction::TYPE_DESCRIPTION)
        ->setValue($object->getDescription()),
      id(new PhabricatorTextEditField())
        ->setKey('domain')
        ->setLabel(pht('Custom Domain'))
        ->setDescription(pht('Blog domain name.'))
        ->setConduitDescription(pht('Change the blog domain.'))
        ->setConduitTypeDescription(pht('New blog domain.'))
        ->setValue($object->getDomain())
        ->setTransactionType(PhameBlogTransaction::TYPE_DOMAIN),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setTransactionType(PhameBlogTransaction::TYPE_STATUS)
        ->setIsConduitOnly(true)
        ->setOptions(PhameBlog::getStatusNameMap())
        ->setDescription(pht('Active or archived status.'))
        ->setConduitDescription(pht('Active or archive the blog.'))
        ->setConduitTypeDescription(pht('New blog status constant.'))
        ->setValue($object->getStatus()),
    );
  }

}
