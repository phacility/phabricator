IFS=$'\n\t'

echo ">>> Login to docker hub with access to ygreif/phab"
docker login

echo ">>> Building ygreif/phab"
docker build -t ygreif/phab:latest .

echo ">>> Committing new container to ygreif/phab:latest"
docker push ygreif/phab:latest
