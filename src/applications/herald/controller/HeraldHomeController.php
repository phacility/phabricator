<?php

final class HeraldHomeController extends HeraldController {

  private $contentType;
  private $ruleType;

  public function willProcessRequest(array $data) {
    $this->contentType = idx($data, 'content_type');
    $this->ruleType = idx($data, 'rule_type');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $phids = $request->getArr('set_phid');
      $phid = head($phids);

      $uri = $request->getRequestURI();
      if ($phid) {
        $uri = $uri->alter('phid', nonempty($phid, null));
      }

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $query = new HeraldRuleQuery();

    $content_type_map = HeraldContentTypeConfig::getContentTypeMap();
    if (empty($content_type_map[$this->contentType])) {
      $this->contentType = head_key($content_type_map);
    }
    $content_desc = $content_type_map[$this->contentType];

    $query->withContentTypes(array($this->contentType));

    $is_admin_page = false;
    $show_author = false;
    $show_rule_type = false;
    $can_create = false;
    $has_author_filter = false;
    $author_filter_phid = null;

    switch ($this->ruleType) {
      case 'all':
        if (!$user->getIsAdmin()) {
          return new Aphront400Response();
        }
        $is_admin_page = true;
        $show_rule_type = true;
        $show_author = true;
        $has_author_filter = true;
        $author_filter_phid = $request->getStr('phid');
        if ($author_filter_phid) {
          $query->withAuthorPHIDs(array($author_filter_phid));
        }
        $rule_desc = 'All';
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        $query->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_GLOBAL));
        $can_create = true;
        $rule_desc = 'Global';
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      default:
        $this->ruleType = HeraldRuleTypeConfig::RULE_TYPE_PERSONAL;
        $query->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL));
        $query->withAuthorPHIDs(array($user->getPHID()));
        $can_create = true;
        $rule_desc = 'Personal';
        break;
    }

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getStr('offset'));

    $rules = $query->executeWithOffsetPager($pager);

    $need_phids = mpull($rules, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($need_phids);

    $list_view = id(new HeraldRuleListView())
      ->setRules($rules)
      ->setShowAuthor($show_author)
      ->setShowRuleType($show_rule_type)
      ->setHandles($handles)
      ->setUser($user);

    $panel = new AphrontPanelView();
    $panel->appendChild($list_view);
    $panel->appendChild($pager);
    $panel->setNoBackground();

    $panel->setHeader("Herald: {$rule_desc} Rules for {$content_desc}");

    if ($can_create) {
      $panel->addButton(
        phutil_tag(
          'a',
          array(
            'href' => '/herald/new/'.$this->contentType.'/'.$this->ruleType.'/',
            'class' => 'green button',
          ),
          'Create New Herald Rule'));
    }


    $nav = $this->renderNav();
    $nav->selectFilter('view/'.$this->contentType.'/'.$this->ruleType);

    if ($has_author_filter) {
      $nav->appendChild($this->renderAuthorFilter($author_filter_phid));
    }

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Herald',
        'admin' => $is_admin_page,
      ));
  }

  private function renderAuthorFilter($phid) {
    $user = $this->getRequest()->getUser();
    if ($phid) {
      $handle = PhabricatorObjectHandleData::loadOneHandle(
        $phid,
        $user);
      $tokens = array(
        $phid => $handle->getFullName(),
      );
    } else {
      $tokens = array();
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('set_phid')
          ->setValue($tokens)
          ->setLimit(1)
          ->setLabel('Filter Author')
          ->setDataSource('/typeahead/common/accounts/'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Apply Filter'));

    $filter = new AphrontListFilterView();
    $filter->appendChild($form);
    return $filter;
  }


}
