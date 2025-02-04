#!/bin/bash
#
# Easysoft persistence library
# Used for bringing persistence capabilities to applications that don't have clear separation of data and logic

# shellcheck disable=SC1091

# Load Generic Libraries
. /opt/easysoft/scripts/libfs.sh
. /opt/easysoft/scripts/libos.sh
. /opt/easysoft/scripts/liblog.sh
. /opt/easysoft/scripts/libversion.sh

# Functions

########################
# Persist an application directory
# Globals:
#   EASYSOFT_ROOT_DIR
#   EASYSOFT_VOLUME_DIR
# Arguments:
#   $1 - App folder name
#   $2 - List of app files to persist
# Returns:
#   true if all steps succeeded, false otherwise
#########################
persist_app() {
    local -r app="${1:?missing app}"
    local -a files_to_restore
    read -r -a files_to_persist <<< "$(tr ',;:' ' ' <<< "$2")"
    local -r install_dir="${EASYSOFT_ROOT_DIR}/${app}"
    local -r persist_dir="${EASYSOFT_VOLUME_DIR}/${app}"
    # Persist the individual files
    if [[ "${#files_to_persist[@]}" -le 0 ]]; then
        warn "No files are configured to be persisted"
        return
    fi
    pushd "$install_dir" >/dev/null || exit
    local file_to_persist_relative file_to_persist_destination file_to_persist_destination_folder
    local -r tmp_file="/tmp/perms.acl"
    for file_to_persist in "${files_to_persist[@]}"; do
        if [[ ! -f "$file_to_persist" && ! -d "$file_to_persist" ]]; then
            error "Cannot persist '${file_to_persist}' because it does not exist"
            return 1
        fi
        file_to_persist_relative="$(relativize "$file_to_persist" "$install_dir")"
        file_to_persist_destination="${persist_dir}/${file_to_persist_relative}"
        file_to_persist_destination_folder="$(dirname "$file_to_persist_destination")"
        # Get original permissions for existing files, which will be applied later
        # Exclude the root directory with 'sed', to avoid issues when copying the entirety of it to a volume
        getfacl -R "$file_to_persist_relative" | sed -E '/# file: (\..+|[^.])/,$!d' > "$tmp_file"
        # Copy directories to the volume
        ensure_dir_exists "$file_to_persist_destination_folder"
        cp -Lr --preserve=links "$file_to_persist_relative" "$file_to_persist_destination_folder"
        # Restore permissions
        pushd "$persist_dir" >/dev/null || exit
        if am_i_root; then
            setfacl --restore="$tmp_file"
        else
            # When running as non-root, don't change ownership
            setfacl --restore=<(grep -E -v '^# (owner|group):' "$tmp_file")
        fi
        popd >/dev/null || exit
    done
    popd >/dev/null || exit
    rm -f "$tmp_file"
    # Install the persisted files into the installation directory, via symlinks
    restore_persisted_app "$@"
}

########################
# Restore a persisted application directory
# Globals:
#   EASYSOFT_ROOT_DIR
#   EASYSOFT_VOLUME_DIR
#   FORCE_MAJOR_UPGRADE
# Arguments:
#   $1 - App folder name
#   $2 - List of app files to restore
# Returns:
#   true if all steps succeeded, false otherwise
#########################
restore_persisted_app() {
    local -r app="${1:?missing app}"
    local -a files_to_restore
    read -r -a files_to_restore <<< "$(tr ',;:' ' ' <<< "$2")"
    local -r install_dir="${EASYSOFT_ROOT_DIR}/${app}"
    local -r persist_dir="${EASYSOFT_VOLUME_DIR}/${app}"
    # Restore the individual persisted files
    if [[ "${#files_to_restore[@]}" -le 0 ]]; then
        warn "No persisted files are configured to be restored"
        return
    fi
    local file_to_restore_relative file_to_restore_origin file_to_restore_destination
    for file_to_restore in "${files_to_restore[@]}"; do
        file_to_restore_relative="$(relativize "$file_to_restore" "$install_dir")"
        # We use 'realpath --no-symlinks' to ensure that the case of '.' is covered and the directory is removed
        file_to_restore_origin="$(realpath --no-symlinks "${install_dir}/${file_to_restore_relative}")"
        file_to_restore_destination="$(realpath --no-symlinks "${persist_dir}/${file_to_restore_relative}")"
        rm -rf "$file_to_restore_origin"
        ln -sfn "$file_to_restore_destination" "$file_to_restore_origin"
    done
}

########################
# Check if an application directory was already persisted
# Globals:
#   EASYSOFT_VOLUME_DIR
# Arguments:
#   $1 - App folder name
# Returns:
#   true if all steps succeeded, false otherwise
#########################
is_app_initialized() {
    local -r app="${1:?missing app}"
    local -r persist_dir="${EASYSOFT_VOLUME_DIR}/${app}"
    if ! is_mounted_dir_empty "$persist_dir"; then
        true
    else
        false
    fi
}

########################
# make link source directory to dest directory
# Arguments:
#   $1 - sourcepath
#   $2 - destpath
#   $3 - owner
# Returns:
#   None
#########################
move_then_link() {
    local source="${1:?path is missing}"
    local dest="${2:?path is missing}"
    local owner=${3:-}
    local group=${4:-}

    ensure_dir_exists "$dest" "www-data" "777"

    # 持久化目录没有文件，将代码中需要持久化的文件复制到持久化目录
    if [ ! -e "$source" ];then
        mv "$dest" "$(dirname "$source")"
    fi

    # 代码中有需要持久化的目录，将代码中的目录改名
    if [ -e "$dest" ] && [ ! -L "$dest" ];then
        mv "$dest" "$dest.$$"
    fi

    [ ! -L "$dest" ] && ln -s "$source" "$dest"

    if [[ -n $group ]]; then
        chown -h "$owner":"$group" "$dest"
    else
        chown -h "$owner":"$owner" "$dest"
    fi
}