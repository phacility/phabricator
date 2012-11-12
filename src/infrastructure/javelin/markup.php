<?php

function javelin_render_tag(
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

  return phutil_render_tag($tag, $attributes, $content);
}


function phabricator_render_form(PhabricatorUser $user, $attributes, $content) {
  if (strcasecmp(idx($attributes, 'method'), 'POST') == 0 &&
      !preg_match('#^(https?:|//)#', idx($attributes, 'action'))) {
    $content = phabricator_render_form_magic($user).$content;
  }
  return javelin_render_tag('form', $attributes, $content);
}

function phabricator_render_form_magic(PhabricatorUser $user) {
  return
    phutil_render_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => AphrontRequest::getCSRFTokenName(),
        'value' => $user->getCSRFToken(),
      )).
    phutil_render_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => '__form__',
        'value' => true,
      ));
}

