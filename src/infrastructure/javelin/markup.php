<?php

function javelin_tag(
  $tag,
  array $attributes = array(),
  $content = null) {

  if (isset($attributes['sigil']) ||
      isset($attributes['meta'])  ||
      isset($attributes['mustcapture'])) {
    foreach ($attributes as $k => $v) {
      switch ($k) {
        case 'sigil':
          if ($v !== null) {
            $attributes['data-sigil'] = $v;
          }
          unset($attributes[$k]);
          break;
        case 'meta':
          if ($v !== null) {
            $response = CelerityAPI::getStaticResourceResponse();
            $id = $response->addMetadata($v);
            $attributes['data-meta'] = $id;
          }
          unset($attributes[$k]);
          break;
        case 'mustcapture':
          if ($v) {
            $attributes['data-mustcapture'] = '1';
          } else {
            unset($attributes['data-mustcapture']);
          }
          unset($attributes[$k]);
          break;
      }
    }
  }

  return phutil_tag($tag, $attributes, $content);
}

function phabricator_form(PhabricatorUser $user, $attributes, $content) {
  $body = array();

  $http_method = idx($attributes, 'method');
  $is_post = (strcasecmp($http_method, 'POST') === 0);

  $http_action = idx($attributes, 'action');
  $is_absolute_uri = preg_match('#^(https?:|//)#', $http_action);

  if ($is_post) {
    if ($is_absolute_uri) {
      $is_dev = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');
      if ($is_dev) {
        $form_domain = id(new PhutilURI($http_action))
          ->getDomain();
        $host_domain = id(new PhutilURI(PhabricatorEnv::getURI('/')))
          ->getDomain();

        if (strtolower($form_domain) == strtolower($host_domain)) {
          throw new Exception(
            pht(
              "You are building a <form /> that submits to Phabricator, but ".
              "has an absolute URI in its 'action' attribute ('%s'). To avoid ".
              "leaking CSRF tokens, Phabricator does not add CSRF information ".
              "to forms with absolute URIs. Instead, use a relative URI.",
              $http_action));
        }
      }
    } else {
      $body[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => AphrontRequest::getCSRFTokenName(),
          'value' => $user->getCSRFToken(),
        ));

      $body[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => '__form__',
          'value' => true,
        ));
    }
  }

  if (is_array($content)) {
    $body = array_merge($body, $content);
  } else {
    $body[] = $content;
  }

  return javelin_tag('form', $attributes, $body);
}
