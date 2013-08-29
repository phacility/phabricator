<?php

final class HeraldRuleSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('contentType', $request->getStr('contentType'));
    $saved->setParameter('ruleType', $request->getStr('ruleType'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new HeraldRuleQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    $content_type = $saved->getParameter('contentType');
    $content_type = idx($this->getContentTypeValues(), $content_type);
    if ($content_type) {
      $query->withContentTypes(array($content_type));
    }

    $rule_type = $saved->getParameter('ruleType');
    $rule_type = idx($this->getRuleTypeValues(), $rule_type);
    if ($rule_type) {
      $query->withRuleTypes(array($rule_type));
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $phids = $saved_query->getParameter('authorPHIDs', array());
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->requireViewer())
      ->loadHandles();
    $author_tokens = mpull($handles, 'getFullName', 'getPHID');

    $content_type = $saved_query->getParameter('contentType');
    $rule_type = $saved_query->getParameter('ruleType');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_tokens))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('contentType')
          ->setLabel(pht('Content Type'))
          ->setValue($content_type)
          ->setOptions($this->getContentTypeOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('ruleType')
          ->setLabel(pht('Rule Type'))
          ->setValue($rule_type)
          ->setOptions($this->getRuleTypeOptions()));
  }

  protected function getURI($path) {
    return '/herald/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getContentTypeOptions() {
    return array(
      '' => pht('(All Content Types)'),
    ) + HeraldAdapter::getEnabledAdapterMap();
  }

  private function getContentTypeValues() {
    return array_fuse(array_keys(HeraldAdapter::getEnabledAdapterMap()));
  }

  private function getRuleTypeOptions() {
    return array(
      '' => pht('(All Rule Types)'),
    ) + HeraldRuleTypeConfig::getRuleTypeMap();
  }

  private function getRuleTypeValues() {
    return array_fuse(array_keys(HeraldRuleTypeConfig::getRuleTypeMap()));
  }

}
