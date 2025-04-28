#!/usr/bin/env bash

if sed --version >/dev/null 2>&1; then
  SED_I=( -i )
else
  SED_I=( -i '' )
fi

set -euo pipefail

defaultPort=8080
defaultDbPort=5432

read -rp "Project name (lowercase, no spaces): " projectName
read -rp "Docker username [$projectName]: " dockerUsername
dockerUsername=${dockerUsername:-$projectName}

read -rp "Symfony app port [$defaultPort]: " port
port=${port:-$defaultPort}

read -rp "PostgreSQL port [$defaultDbPort]: " dbPort
dbPort=${dbPort:-$defaultDbPort}

cp docker-compose.yml.dist docker-compose.yml
cp .env.dist .env

read -rp "Initialize project \"$projectName\" on ports $port/$dbPort? [y/N] " yn
case "$yn" in
  [Yy]* )
    sed "${SED_I[@]}" "s/nginx_port/$port/g" docker-compose.yml
    sed "${SED_I[@]}" "s/database_port/$dbPort/g" docker-compose.yml
    sed "${SED_I[@]}" "s/project_user/$dockerUsername/g" docker/dev/Dockerfile
    sed "${SED_I[@]}" "s/project_/${projectName}_/g" docker-compose.yml docker/dev/nginx/project_local.conf
    echo "Building containers…"
    make install
    ;;
  * )
    echo "Aborted."; exit 1
    ;;
esac
