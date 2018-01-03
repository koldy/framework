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
 * @link http://koldy.net/docs/session/db
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
     */
    protected function getAdapter(): AbstractAdapter
    {
        return KoldyDb::getAdapter($this->getAdapterConnection());
    }

    /**
     * @param string $save_path
     * @param string $sessionid
     *
     * @return bool
     * @throws Exception
     */
    public function open($save_path, $sessionid)
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
    public function close()
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

        } catch (DbException $e) {
            throw $e;

        } finally {
            if ($this->disableLog) {
                Log::restoreTemporaryDisablement();
            }
        }

        return $r;
    }

    /**
     * @param string $sessionid
     *
     * @return string
     * @throws DbException
     */
    public function read($sessionid)
    {
        $sess = $this->getDbData($sessionid);

        if ($sess === null) {
            return '';
        } else {
            if ($this->getAdapter() instanceof PostgreSQL) {
                return hex2bin(stream_get_contents($sess->data));
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

        $sess = $this->getDbData($sessionid);

        if ($this->disableLog) {
            Log::temporaryDisable('sql');
        }

        if ($sess === null) {
            // the record doesn't exists in database, lets insert it
            $data['id'] = $sessionid;

            try {
                $adapter->insert($this->getTableName(), $data)->exec();
                return true;

            } catch (DbException $e) {
                Log::emergency($e);
                return false;

            } finally {
                if ($this->disableLog) {
                    Log::restoreTemporaryDisablement();
                }

            }

        } else {
            // the record data already exists in db

            try {
                $adapter->update($this->getTableName(), $data)->where('id', $sessionid)->exec();
                return true;

            } catch (DbException $e) {
                Log::emergency($e);
                return false;

            } finally {
                if ($this->disableLog) {
                    Log::restoreTemporaryDisablement();
                }

            }
        }
    }

    /**
     * @param string $sessionid
     *
     * @return bool
     */
    public function destroy($sessionid)
    {
        if ($this->disableLog) {
            Log::temporaryDisable('sql');
        }

        try {
            $this->getAdapter()->delete($this->getTableName())->where('id', $sessionid)->exec();
            return true;

        } catch (DbException $e) {
            Log::emergency($e);
            return false;

        } finally {
            if ($this->disableLog) {
                Log::restoreTemporaryDisablement();
            }

        }
    }

    /**
     * @param int $maxlifetime
     *
     * @return bool
     */
    public function gc($maxlifetime)
    {
        $timestamp = time() - $maxlifetime;

        if ($this->disableLog) {
            Log::temporaryDisable('sql');
        }

        try {
            $this->getAdapter()->delete($this->getTableName())->where('time', '<', $timestamp)->exec();
            return true;

        } catch (DbException $e) {
            Log::emergency($e);
            return false;

        } finally {
            if ($this->disableLog) {
                Log::restoreTemporaryDisablement();
            }
        }
    }

}
