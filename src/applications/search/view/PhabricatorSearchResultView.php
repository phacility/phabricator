<?php

/**
 * @group search
 */
final class PhabricatorSearchResultView extends AphrontView {

  private $handle;
  private $query;
  private $object;

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setQuery(PhabricatorSearchQuery $query) {
    $this->query = $query;
    return $this;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function render() {
    $handle = $this->handle;
    if (!$handle->isComplete()) {
      return;
    }

    $type_name = nonempty($handle->getTypeName(), 'Document');

    require_celerity_resource('phabricator-search-results-css');

    $link = phutil_tag(
      'a',
      array(
        'href' => $handle->getURI(),
      ),
      PhabricatorEnv::getProductionURI($handle->getURI()));

    $img = $handle->getImageURI();

    if ($img) {
      $img = phutil_tag(
        'div',
        array(
          'class' => 'result-image',
          'style' => "background-image: url('{$img}');",
        ),
        '');
    }

    switch ($handle->getType()) {
      case PhabricatorPHIDConstants::PHID_TYPE_CMIT:
        $object_name = $handle->getName();
        if ($this->object) {
          $data = $this->object->loadOneRelative(
            new PhabricatorRepositoryCommitData(),
            'commitID');
          if ($data && strlen($data->getSummary())) {
            $object_name = $handle->getName().': '.$data->getSummary();
          }
        }
        break;
      default:
        $object_name = $handle->getFullName();
        break;
    }

    return hsprintf(
      '<div class="phabricator-search-result">'.
        '%s'.
        '<div class="result-desc">'.
          '%s'.
          '<div class="result-type">%s &middot; %s</div>'.
        '</div>'.
        '<div style="clear: both;"></div>'.
      '</div>',
      $img,
      phutil_tag(
        'a',
        array(
          'class' => 'result-name',
          'href' => $handle->getURI(),
        ),
        $this->emboldenQuery($object_name)),
      $type_name,
      $link);
  }

  private function emboldenQuery($str) {
    if (!$this->query) {
      return $str;
    }

    $query = $this->query->getQuery();

    $quoted_regexp = '/"([^"]*)"/';
    $matches = array(1 => array());
    preg_match_all($quoted_regexp, $query, $matches);
    $quoted_queries = $matches[1];
    $query = preg_replace($quoted_regexp, '', $query);

    $query = preg_split('/\s+[+|]?/', $query);
    $query = array_filter($query);
    $query = array_merge($query, $quoted_queries);
    $str = phutil_escape_html($str);
    foreach ($query as $word) {
      $word = phutil_escape_html($word);
      $word = preg_quote($word, '/');
      $word = preg_replace('/\\\\\*$/', '\w*', $word);
      $str = preg_replace(
        '/(?:^|\b)('.$word.')(?:\b|$)/i',
        '<strong>\1</strong>',
        $str);
    }
    return phutil_safe_html($str);
  }

}
