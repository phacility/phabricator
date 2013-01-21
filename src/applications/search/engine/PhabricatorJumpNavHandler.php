<?php

final class PhabricatorJumpNavHandler {
  public static function jumpPostResponse($jump) {
    $jump = trim($jump);
    $help_href = PhabricatorEnv::getDocLink(
      'article/Jump_Nav_User_Guide.html');

    $patterns = array(
      '/^help/i'                  => 'uri:'.$help_href,
      '/^a$/i'                    => 'uri:/audit/',
      '/^f$/i'                    => 'uri:/feed/',
      '/^d$/i'                    => 'uri:/differential/',
      '/^r$/i'                    => 'uri:/diffusion/',
      '/^t$/i'                    => 'uri:/maniphest/',
      '/^p$/i'                    => 'uri:/project/',
      '/^u$/i'                    => 'uri:/people/',
      '/^r([A-Z]+)$/'             => 'repository',
      '/^r([A-Z]+)(\S+)$/'        => 'commit',
      '/^d(\d+)$/i'               => 'revision',
      '/^t(\d+)$/i'               => 'task',
      '/^p(\d+)$/i'               => 'paste',
      '/^p\s+(.+)$/i'             => 'project',
      '/^u\s+(\S+)$/i'            => 'user',
      '/^task:\s*(.+)/i'          => 'create-task',
      '/^(?:s|symbol)\s+(\S+)/i'  => 'find-symbol',
    );


    foreach ($patterns as $pattern => $effect) {
      $matches = null;
      if (preg_match($pattern, $jump, $matches)) {
        if (!strncmp($effect, 'uri:', 4)) {
          return id(new AphrontRedirectResponse())
            ->setURI(substr($effect, 4));
        } else {
          switch ($effect) {
            case 'repository':
              return id(new AphrontRedirectResponse())
                ->setURI('/diffusion/'.$matches[1].'/');
            case 'commit':
              return id(new AphrontRedirectResponse())
                ->setURI('/'.$matches[0]);
            case 'revision':
              return id(new AphrontRedirectResponse())
                ->setURI('/D'.$matches[1]);
            case 'task':
              return id(new AphrontRedirectResponse())
                ->setURI('/T'.$matches[1]);
            case 'user':
              return id(new AphrontRedirectResponse())
                ->setURI('/p/'.$matches[1].'/');
            case 'paste':
              return id(new AphrontRedirectResponse())
                ->setURI('/P'.$matches[1]);
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
            case 'create-task':
              return id(new AphrontRedirectResponse())
                ->setURI('/maniphest/task/create/?title='
                  .phutil_escape_uri($matches[1]));
            default:
              throw new Exception("Unknown jump effect '{$effect}'!");
          }
        }
      }
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
