#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-init}"
APP_DIR="${APP_DIR:-/var/www/api}"

VAR_DIR="${APP_DIR}/var"
STORAGE_DIR="${VAR_DIR}/storage/images"
JWT_DIR="${APP_DIR}/config/jwt"
DB_FILE="${VAR_DIR}/data.db"

required_dirs=(
  "${VAR_DIR}"
  "${VAR_DIR}/log"
  "${STORAGE_DIR}"
  "${STORAGE_DIR}/original"
  "${STORAGE_DIR}/thumbnail"
  "${STORAGE_DIR}/resized"
  "${STORAGE_DIR}/pending"
  "${JWT_DIR}"
)

init_permissions() {
  umask 0002

  for dir in "${required_dirs[@]}"; do
    mkdir -p "${dir}"
    chmod 775 "${dir}"
  done

  touch "${DB_FILE}"
  chmod 664 "${DB_FILE}"

  chgrp -R www-data "${VAR_DIR}" "${JWT_DIR}" 2>/dev/null || true

  if [ -f "${JWT_DIR}/private.pem" ]; then
    chmod 640 "${JWT_DIR}/private.pem"
  fi

  if [ -f "${JWT_DIR}/public.pem" ]; then
    chmod 644 "${JWT_DIR}/public.pem"
  fi
}

doctor_permissions() {
  local failed=0

  for dir in "${required_dirs[@]}"; do
    if [ ! -d "${dir}" ]; then
      echo "missing directory: ${dir}"
      failed=1
      continue
    fi

    if [ ! -w "${dir}" ]; then
      echo "not writable: ${dir}"
      failed=1
    fi
  done

  if [ ! -f "${DB_FILE}" ]; then
    echo "missing sqlite database file: ${DB_FILE}"
    failed=1
  elif [ ! -w "${DB_FILE}" ]; then
    echo "sqlite database file is not writable: ${DB_FILE}"
    failed=1
  fi

  if [ -f "${JWT_DIR}/private.pem" ] && [ ! -r "${JWT_DIR}/private.pem" ]; then
    echo "jwt private key exists but is not readable: ${JWT_DIR}/private.pem"
    failed=1
  fi

  if [ -f "${JWT_DIR}/public.pem" ] && [ ! -r "${JWT_DIR}/public.pem" ]; then
    echo "jwt public key exists but is not readable: ${JWT_DIR}/public.pem"
    failed=1
  fi

  if [ "${failed}" -ne 0 ]; then
    echo "permission doctor failed"
    return 1
  fi

  echo "permission doctor: ok"
}

case "${MODE}" in
  init)
    init_permissions
    ;;
  doctor)
    doctor_permissions
    ;;
  *)
    echo "usage: $0 [init|doctor]"
    exit 1
    ;;
esac
