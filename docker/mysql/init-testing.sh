#!/bin/bash
set -euo pipefail
# Only the isolated database is writable by this dedicated test account.
[[ "$MYSQL_TEST_PASSWORD" =~ ^[a-f0-9]{48}$ ]] || { echo 'Invalid generated test credential format.' >&2; exit 1; }
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root <<SQL
CREATE DATABASE IF NOT EXISTS biblioteca_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'biblioteca_test'@'%' IDENTIFIED BY '${MYSQL_TEST_PASSWORD}';
GRANT ALL PRIVILEGES ON biblioteca_testing.* TO 'biblioteca_test'@'%';
SQL
