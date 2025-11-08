#!/usr/bin/env bash
set -euo pipefail

REMOTE_NAME="${GIT_REMOTE_NAME:-origin}"
BRANCH_NAME="${1:-$(git rev-parse --abbrev-ref HEAD)}"
REMOTE_URL="${2:-${GIT_REMOTE_URL:-}}"

if [[ -z "${BRANCH_NAME}" ]]; then
  echo "Unable to determine branch name. Pass it explicitly as the first argument." >&2
  exit 1
fi

if git remote get-url "${REMOTE_NAME}" >/dev/null 2>&1; then
  EXISTING_URL=$(git remote get-url "${REMOTE_NAME}")
else
  EXISTING_URL=""
fi

if [[ -z "${REMOTE_URL}" ]]; then
  if [[ -z "${EXISTING_URL}" ]]; then
    cat <<USAGE >&2
Usage: ${0##*/} [branch] [remote-url]

Sets up a Git remote named "${REMOTE_NAME}" and pushes the requested branch.
Provide the remote URL via the second argument or the GIT_REMOTE_URL environment variable.
USAGE
    exit 1
  fi
  REMOTE_URL="${EXISTING_URL}"
fi

if [[ -z "${EXISTING_URL}" ]]; then
  echo "Adding remote ${REMOTE_NAME} -> ${REMOTE_URL}" >&2
  git remote add "${REMOTE_NAME}" "${REMOTE_URL}"
elif [[ "${EXISTING_URL}" != "${REMOTE_URL}" ]]; then
  echo "Updating remote ${REMOTE_NAME} URL" >&2
  git remote set-url "${REMOTE_NAME}" "${REMOTE_URL}"
fi

echo "Pushing ${BRANCH_NAME} to ${REMOTE_NAME}" >&2
exec git push -u "${REMOTE_NAME}" "${BRANCH_NAME}"
