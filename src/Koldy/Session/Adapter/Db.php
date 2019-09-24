<?php declare(strict_types=1);

namespace Koldy\Session\Adapter;

use Koldy\Db as KoldyDb;
use Koldy\Db\Adapter\PostgreSQL;
use Koldy\Db\Exception as DbException;
use Koldy\Db\Adapter\AbstractAdapter;
use Koldy\Session\Exception;
use Koldy\Log;
use SessionHandlerInterface;
use stdClass;

/**
 * This is session handler that will store session data into database.
 *
 * You MUSTN'T use it! This class will use PHP internally by it self. You just
 * configure it all and watch the magic.
 *
 * @link https://koldy.net/framework/docs/2.0/session/database.md
 */
class Db implements SessionHandlerInterface
{

    /**
     * The 'options' part from config/session.php
     *
     * @var array
     */
    protected $config = [];

    /**
     * Flag if log should be disabled for session related database queries
     */
    protected $disableLog = true;

    /**
     * Construct the Db Session storage handler
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (array_key_exists('log', $config)) {
            $this->disableLog = (bool)$config['log'] === true ? false : true;
        }
    }

    /**
     * @return string
     */
    protected function getTableName(): string
    {
        return $this->config['table'];
    }

    /**
     * @return null|string
     */
    protected function getAdapterConnection(): ?string
    {
        return $this->config['connection'];
    }

	/**
	 * @return AbstractAdapter
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    protected function getAdapter(): AbstractAdapter
    {
        return KoldyDb::getAdapter($this->getAdapterConnection());
    }

	/**
	 * @param string $save_path
	 * @param string $session_name
	 *
	 * @return bool
	 * @throws Exception
	 */
    public function open($save_path, $session_name): bool
    {
        if (!array_key_exists('connection', $this->config)) {
            throw new Exception('Connection parameter is not defined in session\'s DB adapter options');
        }

        if (!isset($this->config['table'])) {
            throw new Exception('\'table\' parameter is not defined in session\'s DB adapter options');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

	/**
	 * Get the session data from database
	 *
	 * @param string $sessionid
	 *
	 * @return null|stdClass if data doesn't exist in database
	 * @throws DbException
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    private function getDbData($sessionid): ?stdClass
    {
        $r = null;

        try {
            if ($this->disableLog) {
                Log::temporaryDisable('sql');
            }

            $r = $this->getAdapter()
              ->select($this->getTableName())
              ->field('time')
              ->field('data')
              ->where('id', $sessionid)
              ->fetchFirstObj();

	        if ($this->disableLog) {
		        Log::restoreTemporaryDisablement();
	        }
        } catch (DbException $e) {
	        if ($this->disableLog) {
		        Log::restoreTemporaryDisablement();
	        }

            throw $e;

        }

        return $r;
    }

	/**
	 * @param string $sessionid
	 *
	 * @return string
	 * @throws DbException
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function read($sessionid)
    {
        $sess = $this->getDbData($sessionid);

        if ($sess === null) {
            return '';
        } else {
            if ($this->getAdapter() instanceof PostgreSQL) {
                return hex2bin(is_resource($sess->data) ? stream_get_contents($sess->data) : $sess->data);
            } else {
                return $sess->data;
            }
        }
    }

	/**
	 * @param string $sessionid
	 * @param string $sessiondata
	 *
	 * @return bool
	 * @throws DbException
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 * @throws \Koldy\Json\Exception
	 */
    public function write($sessionid, $sessiondata)
    {
	    $adapter = $this->getAdapter();

	    $data = [
		    'time' => time(),
		    'data' => $sessiondata
	    ];

	    if ($adapter instanceof PostgreSQL) {
		    $data['data'] = bin2hex($data['data']);
	    }

	    if ($this->disableLog) {
		    Log::temporaryDisable('sql');
	    }


	    // when we're writing session data to database, we'll assume that there's much more update statements than inserts,
	    // so we'll try to update first, then we'll insert if no record has changed

	    // 1. UPDATE
	    $shouldInsert = false;

	    try {
		    /** @var \Koldy\Db\Query\Statement $koldyStmt */
		    $koldyStmt = $adapter->update($this->getTableName(), $data)->where('id', $sessionid)->exec();
		    $pdoStmt = $koldyStmt->getLastQuery()->getStatement();
		    $shouldInsert = $pdoStmt->rowCount() === 0;
	    } catch (DbException $e) {
		    // something went wrong with database
		    Log::emergency($e);

		    if ($this->disableLog) {
			    Log::restoreTemporaryDisablement();
		    }

		    return false;
	    }

	    // 2. INSERT
	    if ($shouldInsert) {
		    // update statement didn't update any record, so let's insert new record in session table

		    try {
			    $data['id'] = $sessionid;
			    $adapter->insert($this->getTableName(), $data)->exec();
		    } catch (DbException $e) {
			    // for some reason, PHP internally can call session_write_close twice and both times, update statement would return 0 and insert
			    // would be called two times - first insert will work, but the 2nd one will fail because of duplicate session key in database
			    // therefore, we'll try to update this one more time before throwing an exception

			    try {
				    /** @var \Koldy\Db\Query\Statement $koldyStmt */
				    $adapter->update($this->getTableName(), $data)->where('id', $sessionid)->exec();
			    } catch (DbException $e2) {
				    // something went wrong with database
				    Log::emergency($e2);

				    if ($this->disableLog) {
					    Log::restoreTemporaryDisablement();
				    }

				    return false;
			    }

		    }
	    }

	    if ($this->disableLog) {
		    Log::restoreTemporaryDisablement();
	    }

	    return true;
    }

	/**
	 * @param string $sessionid
	 *
	 * @return bool
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function destroy($sessionid): bool
    {
        if ($this->disableLog) {
            Log::temporaryDisable('sql');
        }

        try {
            $this->getAdapter()->delete($this->getTableName())->where('id', $sessionid)->exec();

	        if ($this->disableLog) {
		        Log::restoreTemporaryDisablement();
	        }

        } catch (DbException $e) {
            Log::emergency($e);

	        if ($this->disableLog) {
		        Log::restoreTemporaryDisablement();
	        }

            return false;

        }

	    return true;
    }

	/**
	 * @param int $maxlifetime
	 *
	 * @return bool
	 * @throws \Koldy\Config\Exception
	 * @throws \Koldy\Exception
	 */
    public function gc($maxlifetime)
    {
        $timestamp = time() - $maxlifetime;

        if ($this->disableLog) {
            Log::temporaryDisable('sql');
        }

        try {
            $this->getAdapter()->delete($this->getTableName())->where('time', '<', $timestamp)->exec();

	        if ($this->disableLog) {
		        Log::restoreTemporaryDisablement();
	        }

        } catch (DbException $e) {
            Log::emergency($e);

	        if ($this->disableLog) {
		        Log::restoreTemporaryDisablement();
	        }

            return false;

        }

	    return true;
    }

}
