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
    $saved->setParameter(
      'disabled',
      $this->readBoolFromRequest($request, 'disabled'));

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

    $disabled = $saved->getParameter('disabled');
    if ($disabled !== null) {
      $query->withDisabled($disabled);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $phids = $saved_query->getParameter('authorPHIDs', array());
    $author_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $content_type = $saved_query->getParameter('contentType');
    $rule_type = $saved_query->getParameter('ruleType');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_handles))
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
          ->setOptions($this->getRuleTypeOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('disabled')
          ->setLabel(pht('Rule Status'))
          ->setValue($this->getBoolFromQuery($saved_query, 'disabled'))
          ->setOptions(
            array(
              '' => pht('Show Enabled and Disabled Rules'),
              'false' => pht('Show Only Enabled Rules'),
              'true' => pht('Show Only Disabled Rules'),
            )));
  }

  protected function getURI($path) {
    return '/herald/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
    }

    $names['active'] = pht('Active');
    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query->setParameter('disabled', false);
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer_phid))
          ->setParameter('disabled', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getContentTypeOptions() {
    return array(
      '' => pht('(All Content Types)'),
    ) + HeraldAdapter::getEnabledAdapterMap($this->requireViewer());
  }

  private function getContentTypeValues() {
    return array_fuse(
      array_keys(
        HeraldAdapter::getEnabledAdapterMap($this->requireViewer())));
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
