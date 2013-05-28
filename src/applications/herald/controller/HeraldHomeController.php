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

    $show_author = false;
    $show_rule_type = false;
    $has_author_filter = false;
    $author_filter_phid = null;

    switch ($this->ruleType) {
      case 'all':
        if (!$user->getIsAdmin()) {
          return new Aphront400Response();
        }
        $show_rule_type = true;
        $show_author = true;
        $has_author_filter = true;
        $author_filter_phid = $request->getStr('phid');
        if ($author_filter_phid) {
          $query->withAuthorPHIDs(array($author_filter_phid));
        }
        $rule_desc = pht('All');
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        $query->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_GLOBAL));
        $rule_desc = pht('Global');
        break;
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
      default:
        $this->ruleType = HeraldRuleTypeConfig::RULE_TYPE_PERSONAL;
        $query->withRuleTypes(array(HeraldRuleTypeConfig::RULE_TYPE_PERSONAL));
        $query->withAuthorPHIDs(array($user->getPHID()));
        $rule_desc = pht('Personal');
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

    $panel->setHeader(
      pht("Herald: %s Rules for %s", $rule_desc, $content_desc));

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Herald Rules'))
          ->setHref($this->getApplicationURI(
            'view/'.$this->contentType.'/'.$this->ruleType)));

    $nav = $this->renderNav();
    $nav->selectFilter('view/'.$this->contentType.'/'.$this->ruleType);

    if ($has_author_filter) {
      $nav->appendChild($this->renderAuthorFilter($author_filter_phid));
    }

    $nav->appendChild($panel);
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Herald'),
        'dust' => true,
        'device' => true,
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
      ->setNoShading(true)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('set_phid')
          ->setValue($tokens)
          ->setLimit(1)
          ->setLabel(pht('Filter Author'))
          ->setDataSource('/typeahead/common/accounts/'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Apply Filter')));

    $filter = new AphrontListFilterView();
    $filter->appendChild($form);
    return $filter;
  }


}
