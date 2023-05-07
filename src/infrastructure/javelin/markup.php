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

  if (isset($attributes['aural'])) {
    if ($attributes['aural']) {
      $class = idx($attributes, 'class', '');
      $class = rtrim('aural-only '.$class);
      $attributes['class'] = $class;
    } else {
      $class = idx($attributes, 'class', '');
      $class = rtrim('visual-only '.$class);
      $attributes['class'] = $class;
      $attributes['aria-hidden'] = 'true';
    }
    unset($attributes['aural']);
  }

  if (isset($attributes['print'])) {
    if ($attributes['print']) {
      $class = idx($attributes, 'class', '');
      $class = rtrim('print-only '.$class);
      $attributes['class'] = $class;

      // NOTE: Alternative print content is hidden from screen readers.
      $attributes['aria-hidden'] = 'true';
    } else {
      $class = idx($attributes, 'class', '');
      $class = rtrim('screen-only '.$class);
      $attributes['class'] = $class;
    }
    unset($attributes['print']);
  }


  return phutil_tag($tag, $attributes, $content);
}

function phabricator_form(PhabricatorUser $user, $attributes, $content) {
  $body = array();

  $http_method = idx($attributes, 'method');
  $is_post = (strcasecmp($http_method, 'POST') === 0);

  $http_action = idx($attributes, 'action');
  $is_absolute_uri = false;
  if ($http_action != null) {
    $is_absolute_uri = preg_match('#^(https?:|//)#', $http_action);
  }

  if ($is_post) {

    // NOTE: We only include CSRF tokens if a URI is a local URI on the same
    // domain. This is an important security feature and prevents forms which
    // submit to foreign sites from leaking CSRF tokens.

    // In some cases, we may construct a fully-qualified local URI. For example,
    // we can construct these for download links, depending on configuration.

    // These forms do not receive CSRF tokens, even though they safely could.
    // This can be confusing, if you're developing for Phabricator and
    // manage to construct a local form with a fully-qualified URI, since it
    // won't get CSRF tokens and you'll get an exception at the other end of
    // the request which is a bit disconnected from the actual root cause.

    // However, this is rare, and there are reasonable cases where this
    // construction occurs legitimately, and the simplest fix is to omit CSRF
    // tokens for these URIs in all cases. The error message you receive also
    // gives you some hints as to this potential source of error.

    if (!$is_absolute_uri) {
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

      // If the profiler was active for this request, keep it active for any
      // forms submitted from this page.
      if (DarkConsoleXHProfPluginAPI::isProfilerRequested()) {
        $body[] = phutil_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => '__profile__',
            'value' => true,
          ));
      }

    }
  }

  if (is_array($content)) {
    $body = array_merge($body, $content);
  } else {
    $body[] = $content;
  }

  return javelin_tag('form', $attributes, $body);
}
