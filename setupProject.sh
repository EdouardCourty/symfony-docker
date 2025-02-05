defaultPort=8080
defaultDbPort=5432

read -rp "What should be the project name? (lowercase, no spaces) " projectName

read -rp "What should be the docker username? [$projectName] " dockerUsername
dockerUsername=${dockerUsername:-$projectName}

read -rp "On what port should the Symfony app run? [$defaultPort] " port
port=${port:-defaultPort}

read -rp "On what port should the PostgreSQL database run? [$defaultDbPort] " dbPort
dbPort=${dbPort:-defaultDbPort}

cp docker-compose.yml.dist docker-compose.yml

while true; do
    read -rp "Create project with name \"$projectName\", docker username \"$dockerUsername\" on port $port? [Y/N] " yn
    case $yn in
        [Yy]* ) sed -i "s/project_/$projectName\_/g" docker-compose.yml; \
          sed -i "s/project_/$projectName\_/g" docker/nginx/project_local.conf; \
          sed -i "s/$defaultPort/$port/g" docker-compose.yml; \
          sed -i "s/database_port/$dbPort/g" docker-compose.yml; \
          sed -i "s/project_user/$dockerUsername/g" docker/dev/Dockerfile; \
          echo "Project initialized, building the containers..."; \
          make install; \
          break;;
        [Nn]* ) echo Aborted.; exit;;
        * ) echo "Please answer yes or no.";;
    esac
done
