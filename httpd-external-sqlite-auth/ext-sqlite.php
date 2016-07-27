#!/usr/local/bin/php
<?php
/*
 * Works in conjunction with mod_authnz_external (https://github.com/kitech/mod_authnz_external)
 * -> Receives on STDIN the username on the 1st line and password on the second line.
 *
 * ext-sqlite.php <database> <SQL>
 *
 * Connects to database and executes SQL. The first field returned
 * must be the password. You don't need to encompass SQL in quotes,
 * all arguments after <database> are interpreted as the SQL query.
 * Use ":u" to bind to the username
 * Use ":p" to bind to the password
 *
 * Example for Apache httpd 2.4:
 *
 * DefineExternalAuth userpass pipe "/bin/ext-sqlite.php /var/auth.sq3 SELECT password FROM users WHERE username = :u"
 * <Location />
 *  AuthType Basic
 *  AuthName "Very secure"
 *  AuthBasicProvider external
 *  AuthExternal userpass
 *  Require valid-user
 * </Location>
 */

// See https://github.com/phokz/pwauth/blob/master/pwauth/INSTALL
define('STATUS_OK',         0);
define('STATUS_UNKNOWN',    1);
define('STATUS_INVALID',    2);
define('STATUS_BLOCKED',    3);
define('STATUS_EXPIRED',    4);
define('STATUS_PW_EXPIRED', 5);
define('STATUS_NOLOGIN',    6);
define('STATUS_MANYFAILS',  7);

define('STATUS_INT_USER',   50);
define('STATUS_INT_ARGS',   51);
define('STATUS_INT_ERR',    52);
define('STATUS_INT_NOROOT', 53);

/**
 * Write an error message to STDERR and append a newline
 * @param string $msg
 */
function wr_err(string $msg)
{
    fwrite(STDERR, $msg . "\n");
}

$file   = '';
$sql    = '';
$user   = '';
$pass   = '';

if(!array_key_exists(1, $argv))
{
    wr_err('No database file specified (1st arg)');
    exit(STATUS_INT_ARGS);
}
else
{
    $file = $argv[1];
    if(!file_exists($file))
    {
        wr_err("Database file {$file} doesn't exist");
        exit(STATUS_INT_ARGS);
    }
}

if(!array_key_exists(2, $argv))
{
    wr_err('Please specify the query to run (2nd arg)');
    exit(STATUS_INT_ARGS);
}
else
{
    for ($i = 2; $i < count($argv); $i++)
    {
        $sql .= ' ' . $argv[$i];
    }
}

$user = trim(fgets(STDIN));
$pass = trim(fgets(STDIN));

fclose(STDIN);

try
{
    $PDO = new \PDO("sqlite:{$file}");
    $PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $PDO->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_NUM);
    $PDO->exec("PRAGMA foreign_keys = ON;");
}
catch (\Exception $ex)
{
    wr_err('Could not connect to the database: ' . $ex->getMessage());
    exit(STATUS_INT_ERR);
}

try
{
    $stmt = $PDO->prepare($sql);

    if(strstr($sql, ':u'))
    {
        $stmt->bindValue(':u', $user);
    }

    $stmt->execute();

    if(count($results = $stmt->fetchAll()) > 0)
    {
        if(strpos($results[0][0], '$2y$') === 0)
        {
            if(password_verify($pass, $results[0][0]))
            {
                exit(STATUS_OK);
            }
            else
            {
                exit(STATUS_INVALID);
            }
        }

        wr_err('Unknown password hash');
        exit(STATUS_INT_ERR);
    }
    else
    {
        exit(STATUS_UNKNOWN);
    }
}
catch (\Exception $ex)
{
    wr_err("Error querying the database: {$ex->getMessage()}");
    exit(STATUS_INT_ERR);
}
