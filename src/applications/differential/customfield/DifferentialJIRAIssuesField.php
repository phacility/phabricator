<?php

final class DifferentialJIRAIssuesField
  extends DifferentialCustomField {

  // TODO: This field needs to actually read storage!
  private $value = null;


  public function getFieldKey() {
    return 'differential:jira-issues';
  }

  public function getFieldName() {
    return pht('JIRA Issues');
  }

  public function getFieldDescription() {
    return pht('Lists associated JIRA issues.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $xobjs = $this->loadDoorkeeperExternalObjects();
    if (!$xobjs) {
      return null;
    }

    $links = array();
    foreach ($xobjs as $xobj) {
      $links[] = id(new DoorkeeperTagView())
        ->setExternalObject($xobj);
    }

    return phutil_implode_html(phutil_tag('br'), $links);
  }

  private function buildDoorkeeperRefs() {
    $provider = PhabricatorAuthProviderOAuth1JIRA::getJIRAProvider();

    $refs = array();
    if ($this->value) {
      foreach ($this->value as $jira_key) {
        $refs[] = id(new DoorkeeperObjectRef())
          ->setApplicationType(DoorkeeperBridgeJIRA::APPTYPE_JIRA)
          ->setApplicationDomain($provider->getProviderDomain())
          ->setObjectType(DoorkeeperBridgeJIRA::OBJTYPE_ISSUE)
          ->setObjectID($jira_key);
      }
    }

    return $refs;
  }

  private function loadDoorkeeperExternalObjects() {
    $refs = $this->buildDoorkeeperRefs();
    if (!$refs) {
      return array();
    }

    $xobjs = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($this->getUser())
      ->withObjectKeys(mpull($refs, 'getObjectKey'))
      ->execute();

    return $xobjs;
  }

}
