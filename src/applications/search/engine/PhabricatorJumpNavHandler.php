<?php

final class PhabricatorJumpNavHandler {

  public static function getJumpResponse(PhabricatorUser $viewer, $jump) {
    $jump = trim($jump);

    $patterns = array(
      '/^a$/i' => 'uri:/audit/',
      '/^f$/i' => 'uri:/feed/',
      '/^d$/i' => 'uri:/differential/',
      '/^r$/i' => 'uri:/diffusion/',
      '/^t$/i' => 'uri:/maniphest/',
      '/^p$/i' => 'uri:/project/',
      '/^u$/i' => 'uri:/people/',
      '/^p\s+(.+)$/i' => 'project',
      '/^u\s+(\S+)$/i' => 'user',
      '/^task:\s*(.+)/i' => 'create-task',
      '/^(?:s)\s+(\S+)/i' => 'find-symbol',
      '/^r\s+(.+)$/i' => 'find-repository',
    );

    foreach ($patterns as $pattern => $effect) {
      $matches = null;
      if (preg_match($pattern, $jump, $matches)) {
        if (!strncmp($effect, 'uri:', 4)) {
          return id(new AphrontRedirectResponse())
            ->setURI(substr($effect, 4));
        } else {
          switch ($effect) {
            case 'user':
              return id(new AphrontRedirectResponse())
                ->setURI('/p/'.$matches[1].'/');
            case 'project':
              $project = self::findCloselyNamedProject($matches[1]);
              if ($project) {
                return id(new AphrontRedirectResponse())
                  ->setURI('/project/view/'.$project->getID().'/');
              } else {
                  $jump = $matches[1];
              }
              break;
            case 'find-symbol':
              $context = '';
              $symbol = $matches[1];
              $parts = array();
              if (preg_match('/(.*)(?:\\.|::|->)(.*)/', $symbol, $parts)) {
                $context = '&context='.phutil_escape_uri($parts[1]);
                $symbol = $parts[2];
              }
              return id(new AphrontRedirectResponse())
                ->setURI("/diffusion/symbol/$symbol/?jump=true$context");
            case 'find-repository':
              $name = $matches[1];
              $repositories = id(new PhabricatorRepositoryQuery())
                ->setViewer($viewer)
                ->withNameContains($name)
                ->execute();
              if (count($repositories) == 1) {
                // Just one match, jump to repository.
                $uri = '/diffusion/'.head($repositories)->getCallsign().'/';
              } else {
                // More than one match, jump to search.
                $uri = urisprintf('/diffusion/?order=name&name=%s', $name);
              }
              return id(new AphrontRedirectResponse())->setURI($uri);
            case 'create-task':
              return id(new AphrontRedirectResponse())
                ->setURI('/maniphest/task/create/?title='
                  .phutil_escape_uri($matches[1]));
            default:
              throw new Exception(pht("Unknown jump effect '%s'!", $effect));
          }
        }
      }
    }

    // If none of the patterns matched, look for an object by name.
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($jump))
      ->execute();

    if (count($objects) == 1) {
      $handle = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($objects, 'getPHID'))
        ->executeOne();

      return id(new AphrontRedirectResponse())->setURI($handle->getURI());
    }

    return null;
  }

  private static function findCloselyNamedProject($name) {
    $project = id(new PhabricatorProject())->loadOneWhere(
      'name = %s',
      $name);
    if ($project) {
      return $project;
    } else { // no exact match, try a fuzzy match
      $projects = id(new PhabricatorProject())->loadAllWhere(
        'name LIKE %~',
        $name);
      if ($projects) {
        $min_name_length = PHP_INT_MAX;
        $best_project = null;
        foreach ($projects as $project) {
          $name_length = strlen($project->getName());
          if ($name_length <= $min_name_length) {
            $min_name_length = $name_length;
            $best_project = $project;
          }
        }
        return $best_project;
      } else {
        return null;
      }
    }
  }
}
