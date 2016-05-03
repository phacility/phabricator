<?php

$table = new PhabricatorRepository();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $repository) {
  $uris = array();

  $serve_http = $repository->getDetail('serve-over-http');
  $http_io = PhabricatorRepositoryURI::IO_DEFAULT;
  $disable_http = false;
  switch ($serve_http) {
    case 'readwrite':
      break;
    case 'readonly':
      $http_io = PhabricatorRepositoryURI::IO_READ;
      break;
    case 'off':
    default:
      $disable_http = true;
      break;
  }

  $serve_ssh = $repository->getDetail('serve-over-ssh');
  $ssh_io = PhabricatorRepositoryURI::IO_DEFAULT;
  $disable_ssh = false;
  switch ($serve_ssh) {
    case 'readwrite':
      break;
    case 'readonly':
      $ssh_io = PhabricatorRepositoryURI::IO_READ;
      break;
    case 'off':
    default:
      $disable_ssh = true;
      break;
  }

  $uris = $repository->newBuiltinURIs();

  foreach ($uris as $uri) {
    $builtin_protocol = $uri->getBuiltinProtocol();
    if ($builtin_protocol == PhabricatorRepositoryURI::BUILTIN_PROTOCOL_SSH) {
      $uri->setIsDisabled((int)$disable_ssh);
      $uri->setIoType($ssh_io);
    } else {
      $uri->setIsDisabled((int)$disable_http);
      $uri->setIoType($http_io);
    }
  }

  if (!$repository->isHosted()) {
    $remote_uri = $repository->getDetail('remote-uri');
    if (strlen($remote_uri)) {
      $uris[] = PhabricatorRepositoryURI::initializeNewURI()
        ->setRepositoryPHID($repository->getPHID())
        ->attachRepository($repository)
        ->setURI($remote_uri)
        ->setCredentialPHID($repository->getCredentialPHID())
        ->setIOType(PhabricatorRepositoryURI::IO_OBSERVE);
    }
  }

  foreach ($uris as $uri) {
    $already_exists = id(new PhabricatorRepositoryURI())->loadOneWhere(
      'repositoryPHID = %s AND uri = %s LIMIT 1',
      $repository->getPHID(),
      $uri->getURI());
    if ($already_exists) {
      continue;
    }

    $uri->save();

    echo tsprintf(
      "%s\n",
      pht(
        'Migrated URI "%s" for repository "%s".',
        $uri->getURI(),
        $repository->getDisplayName()));
  }
}
