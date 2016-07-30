#!/usr/bin/env bash
# Script to backup via dump and copy to a remote host
# Automatically picks the level to the day of the week
# which means each full backup will be on Sunday.
# md5sums the file afterwards.

readonly EX_USAGE=64

usage()
{
    echo "usage: $0 [-0123456789] [-x SSH destination]";
}

LEVEL=$(date +"%w")
DEST=backups@remotehost


while getopts ":0123456789x:" OPT; do
    case ${OPT} in
        x)
            DEST=${OPTARG}
            ;;
        :)
            echo "Option -${OPTARG} requires and argument." >&2
            usage
            exit ${EX_USAGE}
            ;;
        *)
            if [[ ${OPT} =~ ^[0-9]$ ]]; then
                LEVEL=${OPT}
            else
                echo "Option -${OPTARG} unrecognized." >&2
                usage
                exit ${EX_USAGE}
            fi
            ;;
    esac
done

FILENAME=$(hostname).level${LEVEL}.$(date +"%A").$(date +"%Y-%m-%d").dump.gz

/sbin/dump -${LEVEL} -uanL -f - / | /usr/bin/gzip -2 | \
 /usr/bin/ssh -T -x -o Compression=no -c arcfour ${DEST} "dd of=${FILENAME}.tmp; "'MD5=$(md5sum '"${FILENAME}.tmp"' | cut -d " " -f 1);'"mv ${FILENAME}.tmp ${FILENAME}."'${MD5}'

if [ $? -ne 0 ]; then
    exit $?
fi

echo "*** gpart show ***"
/sbin/gpart show
echo "*** /etc/fstab ***"
cat /etc/fstab
echo "*** pkg info ***"
/usr/sbin/pkg info

