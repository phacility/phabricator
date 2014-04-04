UPDATE {$NAMESPACE}_project.project proj,
  {$NAMESPACE}_project.project_profile profile
  SET proj.profileImagePHID = profile.profileImagePHID
  WHERE proj.phid = profile.projectPHID;
