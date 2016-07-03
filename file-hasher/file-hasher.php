<?php
class DB
{
    /**
     * @var string Path to the database file
     */
    private $file;
    /**
     * @var \PDO
     */
    private $PDO;

    const VH_HASHADDED  = 1;
    const VH_HASHFAIL   = 2;
    const VH_HASHOK     = 3;


    public function __construct($file)
    {
        $this->file = $file;

        if(!file_exists($this->file))
        {
            $this->initPDO();
            $this->createPhysDatabase();
        }
        else
        {
            $this->initPDO();
        }
    }

    protected function initPDO()
    {
        $this->PDO = new \PDO("sqlite:{$this->file}");
        $this->PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->PDO->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->PDO->exec("PRAGMA foreign_keys = ON;");
    }

    protected function createPhysDatabase()
    {
        /** @noinspection SqlNoDataSourceInspection */
        $SQL = "CREATE TABLE files (
                  path	TEXT UNIQUE,
	              hash	TEXT,
	              PRIMARY KEY(path)
                );
        ";

        $this->PDO->exec($SQL);
    }

    /**
     * @param $path
     * @param $hash
     * @return int 1 = Hash added to DB (new file), 2 = Hash doesn't match, 3 = Hash matches
     * @throws \RuntimeException If there's a database error
     */
    public function verifyHash($path, $hash)
    {
        $SQL = "SELECT * FROM files WHERE path = :path";

        $stmt = $this->PDO->prepare($SQL);
        $stmt->bindValue(':path', $path);
        $stmt->execute();
        $result = $stmt->fetchAll();
        $stmt->closeCursor();
        if(count($result) === 0)
        {
            if($this->addHash($path, $hash) === false)
            {
                throw new \RuntimeException("Failed to add hash to database!");
            }
            return self::VH_HASHADDED;
        }
        else
        {
            if($result[0]['hash'] !== $hash)
            {
                return self::VH_HASHFAIL;
            }
            else
            {
                return self::VH_HASHOK;
            }
        }
    }

    private function addHash($path, $hash)
    {
        $SQL = "INSERT INTO files (path, hash) VALUES(:path, :hash)";

        $stmt = $this->PDO->prepare($SQL);

        $stmt->bindValue(':path', $path, \PDO::PARAM_STR);
        $stmt->bindValue(':hash', $hash, \PDO::PARAM_STR);

        return $stmt->execute();
    }
}

class IOMeasureResult
{
    public $readBps;
    public $writeBps;

    public function __construct($readBps, $writeBps)
    {
        $this->readBps  = $readBps;
        $this->writeBps = $writeBps;
    }
}

class IOMeasure
{
    private $myPid;
    private $time;
    private $readBytes;
    private $writeBytes;

    /**
     * @return int
     */
    public function getReadBytes() { return $this->readBytes; }

    /**
     * @return int
     */
    public function getWriteBytes() { return $this->writeBytes; }

    /**
     * @return mixed
     */
    public function getTime() { return $this->time; }

    public function __construct()
    {
        $this->myPid        = getmypid();
        $this->time         = microtime(true);

        $measurements       = file_get_contents("/proc/{$this->myPid}/io");
        preg_match('#rchar: (\d+)\nwchar: (\d+)#', $measurements, $matches);
        $this->readBytes    = (int) $matches[1];
        $this->writeBytes   = (int) $matches[2];
    }

    public function stat(\IOMeasure $other)
    {
        $readBps    = 0;
        $writeBps   = 0;

        if($other->getTime() > $this->getTime())
        {
            $a = $other;
            $b = $this;
        }
        else
        {
            $a = $this;
            $b = $other;
        }

        if(($timeDelta  = $a->getTime() - $b->getTime()) > 0)
        {
            $readBps = ($a->getReadBytes() - $b->getReadBytes()) / $timeDelta;
            $writeBps = ($a->getWriteBytes() - $b->getWriteBytes()) / $timeDelta;
        }

        return new \IOMeasureResult($readBps, $writeBps);
    }
}

function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB');

    $bytes  = max($bytes, 0);
    $pow    = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow    = min($pow, count($units) - 1);
    $bytes  /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

$last_line_length = 0;

function echo_replace($line)
{
    global $last_line_length;
    echo "\r";
    for($i = 0; $i < $last_line_length; $i++)
    {
        echo " ";
    }
    echo "\r{$line}";
    $last_line_length = strlen($line);
}

/**
 * @param $cur_dir Directory to build a tree of
 * @return array Array of entries
 */
function build_tree($cur_dir)
{
    $result     = array();
    $cur_dir    = realpath($cur_dir);
    $dir_handle = opendir($cur_dir);

    while(($file = readdir($dir_handle)) !== false)
    {
        $full_path = $cur_dir . DIRECTORY_SEPARATOR . $file;

        if($file == '.' || $file == '..')
        {
            continue;
        }

        if(is_dir($full_path))
        {
            $result = array_merge($result, build_tree($full_path));
        }
        else
        {
            $result[] = $full_path;
        }
    }

    closedir($dir_handle);

    return $result;
}

$options = getopt('d:s:');

if($options === false || !array_key_exists('d', $options) || !array_key_exists('s', $options))
{
    echo "Incorrect command line usage\n";
    echo "file-hasher.php -d directory -s database_file\n";
    exit(64);
}
else
{
    $dir    = $options['d'];
    $dbfile = $options['s'];
}

if(!is_dir($dir))
{
    echo "{$dir} (-d arg) is not a directory";
    exit(64);
}


$hash_added_cnt = 0;
$hash_ok_cnt    = 0;
$hash_fail_cnt  = 0;
$percent        = 0;
$hash_fails     = array();

$stat_begin     = new \IOMeasure();
$stat_now       = new \IOMeasure();
$stat_result    = $stat_now->stat($stat_begin);

echo "[{$dbfile}] Initializing database... ";

$db = new \DB($dbfile);

echo "Done.\n";

echo "[{$dir}] Building file tree... ";

$tree   = build_tree($dir);
$count  = count($tree);

echo "{$count} files.\n";


for($i = 0; $i < $count; $i++)
{
    $human_index    = $i + 1;
    $percent        = str_pad(floor(($human_index / $count) * 100), 3, "0", STR_PAD_LEFT);
    $basename       = basename($tree[$i]);

    $output = sprintf(
        '[%s] [%s%%] [OK:%d, ADDED: %d, FAIL: %d] [READ: %s, %sps] Hashing file %d/%d (%s)',
        $dir,
        $percent,
        $hash_ok_cnt,
        $hash_added_cnt,
        $hash_fail_cnt,
        formatBytes($stat_now->getReadBytes()),
        formatBytes($stat_result->readBps),
        $human_index,
        $count,
        $basename
    );

    echo_replace($output);

    $hash           = md5_file($tree[$i]);

    switch($db->verifyHash($tree[$i], $hash))
    {
        case DB::VH_HASHADDED:
            $hash_added_cnt++;
            break;

        case DB::VH_HASHOK:
            $hash_ok_cnt++;
            break;

        case DB::VH_HASHFAIL:
            $hash_fails[] = $tree[$i];
            $hash_fail_cnt++;
            break;
    }

    $stat_now       = new \IOMeasure();
    $stat_result    = $stat_now->stat($stat_begin);
}

$output = sprintf(
    '[%s] [%s%%] [OK:%d, ADDED: %d, FAIL: %d] [READ: %s, %sps]',
    $dir,
    $percent,
    $hash_ok_cnt,
    $hash_added_cnt,
    $hash_fail_cnt,
    formatBytes($stat_now->getReadBytes()),
    formatBytes($stat_result->readBps)
);

echo_replace($output);
echo "\n";

for($i = 0; $i < count($hash_fails); $i++)
{
    echo "[{$dir}] HASH FAIL: {$hash_fails[$i]}\n";
}

if(count($hash_fails) > 0)
{
    exit(1);
}
else
{
    exit(0);
}
?>