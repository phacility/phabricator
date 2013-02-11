<?php

/**
 * @deprecated Use javelin_tag().
 */
function javelin_render_tag(
  $tag,
  array $attributes = array(),
  $content = null) {

  if (is_array($content)) {
    $content = implode('', $content);
  }

  $html = javelin_tag($tag, $attributes, phutil_safe_html($content));
  return $html->getHTMLContent();
}

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
          $attributes['data-sigil'] = $v;
          unset($attributes[$k]);
          break;
        case 'meta':
          $response = CelerityAPI::getStaticResourceResponse();
          $id = $response->addMetadata($v);
          $attributes['data-meta'] = $id;
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

  if (strcasecmp(idx($attributes, 'method'), 'POST') == 0 &&
      !preg_match('#^(https?:|//)#', idx($attributes, 'action'))) {
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

  if (is_array($content)) {
    $body = array_merge($body, $content);
  } else {
    $body[] = $content;
  }

  return javelin_tag('form', $attributes, $body);
}


/**
 * @deprecated
 */
function phabricator_render_form(PhabricatorUser $user, $attributes, $content) {
  if (is_array($content)) {
    $content = implode('', $content);
  }

  $html = phabricator_form($user, $attributes, phutil_safe_html($content));
  return $html->getHTMLContent();
}

