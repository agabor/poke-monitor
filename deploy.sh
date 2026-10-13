#!/bin/bash

set -e

PLUGIN_SLUG="poke-monitor"
SVN_URL="https://plugins.svn.wordpress.org/poke-monitor"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="$(mktemp -d)"
SVN_DIR="${BUILD_DIR}/svn"
SVN_USERNAME="${SVN_USERNAME:-}"
DRY_RUN="${DRY_RUN:-0}"

die() {
	echo "Error: $1" >&2
	exit 1
}

cleanup() {
	rm -rf "${BUILD_DIR}"
}
trap cleanup EXIT

get_plugin_version() {
	sed -n 's/^[[:space:]*]*Version:[[:space:]]*//p' "${SCRIPT_DIR}/${PLUGIN_SLUG}.php" | head -n 1 | tr -d '\r'
}

get_stable_tag() {
	sed -n 's/^Stable tag:[[:space:]]*//p' "${SCRIPT_DIR}/readme.txt" | head -n 1 | tr -d '\r'
}

register_working_copy_changes() {
	local directory="$1"

	svn add --force --quiet "${directory}"

	svn status "${directory}" | grep '^!' | sed 's/^![[:space:]]*//' | while IFS= read -r missing_path; do
		svn rm --force --quiet "${missing_path}@"
	done
}

command -v svn > /dev/null 2>&1 || die "svn is not installed."
command -v rsync > /dev/null 2>&1 || die "rsync is not installed."

PLUGIN_VERSION="$(get_plugin_version)"
STABLE_TAG="$(get_stable_tag)"

[ -n "${PLUGIN_VERSION}" ] || die "Could not read the plugin version from ${PLUGIN_SLUG}.php."

if [ "${PLUGIN_VERSION}" != "${STABLE_TAG}" ]; then
	die "Plugin version (${PLUGIN_VERSION}) differs from readme stable tag (${STABLE_TAG})."
fi

echo "Checking out ${SVN_URL}"
svn checkout --quiet "${SVN_URL}" "${SVN_DIR}"

cd "${SVN_DIR}"

if [ -d "tags/${PLUGIN_VERSION}" ]; then
	die "Tag ${PLUGIN_VERSION} already exists."
fi

mkdir -p trunk

rsync -a --delete \
	--exclude ".git" \
	--exclude ".gitignore" \
	--exclude ".github" \
	--exclude ".idea" \
	--exclude ".yact" \
	--exclude "dist" \
	--exclude "node_modules" \
	--exclude "pack.sh" \
	--exclude "deploy.sh" \
	--exclude ".wordpress-org" \
	--exclude "*.zip" \
	--exclude "*.md" \
	--exclude ".DS_Store" \
	"${SCRIPT_DIR}/" "${SVN_DIR}/trunk/"

register_working_copy_changes trunk

if [ -d "${SCRIPT_DIR}/.wordpress-org" ]; then
	mkdir -p assets
	rsync -a --delete "${SCRIPT_DIR}/.wordpress-org/" "${SVN_DIR}/assets/"
	register_working_copy_changes assets
fi

svn copy trunk "tags/${PLUGIN_VERSION}"

svn status

if [ "${DRY_RUN}" = "1" ]; then
	echo "Dry run: skipping the commit."
else
	COMMIT_ARGUMENTS=(-m "Release ${PLUGIN_VERSION}")
	if [ -n "${SVN_USERNAME}" ]; then
		COMMIT_ARGUMENTS+=(--username "${SVN_USERNAME}")
	fi
	svn commit "${COMMIT_ARGUMENTS[@]}"
fi

echo "Plugin released successfully: ${PLUGIN_SLUG} ${PLUGIN_VERSION}"