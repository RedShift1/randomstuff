file-hasher
===========
Script to recursively hash files (using MD5) and store the result into
an SQLite database. Later runs will check if the current MD5 checksum
matches the one in the database.
 
Run using
`file-hasher.php -d /my/directory -s /home/user/my-filehashes.db3`

You can have multiple directories use the same database.

Examples
--------
While hashing the output looks like this:

```
$ file-hasher.php -d /vol/export/glenn/Brol -s /home/glenn/file-hashes.db3
[/home/glenn/file-hashes.db3] Initializing database... Done.
[/vol/export/glenn/Brol] Building file tree... 112 files.
[/vol/export/glenn/Brol] [011%] [OK:0, ADDED: 12, FAIL: 0] [READ: 1.35 MiB, 1.25 MiBps] Hashing file 13/112 (Win Utility.exe)
```

Result:
```
$ file-hasher.php -d /vol/export/glenn/Brol -s /home/glenn/file-hashes.db3
[/home/glenn/file-hashes.db3] Initializing database... Done.
[/vol/export/glenn/Brol] Building file tree... 112 files.
[/vol/export/glenn/Brol] [100%] [OK:112, ADDED: 0, FAIL: 0] [READ: 293.25 MiB, 380.09 MiBps]
$
```

When a file has failed a hash:

```
$ file-hasher.php -d /vol/export/glenn/Brol -s /home/glenn/file-hashes.db3
[/home/glenn/file-hashes.db3] Initializing database... Done.
[/vol/export/glenn/Brol] Building file tree... 112 files.
[/vol/export/glenn/Brol] [100%] [OK:111, ADDED: 0, FAIL: 1] [READ: 293.25 MiB, 384.12 MiBps]
[/vol/export/glenn/Brol] HASH FAIL: /vol/export/glenn/Brol/sys.img
$
```