UPDATE {$NAMESPACE}_phame.phame_blog
  SET domainFullURI = CONCAT('http://', domain, '/')
  WHERE domain IS NOT NULL;
