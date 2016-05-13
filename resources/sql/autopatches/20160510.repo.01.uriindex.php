<?php

$repos = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->needURIs(true)
  ->execute();

foreach ($repos as $repo) {
  $repo->updateURIIndex();
}
