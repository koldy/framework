<?php declare(strict_types=1);

namespace Koldy\Session\Adapter;

use Koldy\Db as KoldyDb;
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
 * The table structure is:
 *
 * CREATE TABLE `session` (
 *  `id` varchar(40) NOT NULL,
 *  `time` int(10) unsigned NOT NULL,
 *  `data` text CHARACTER SET utf16 NOT NULL,
 *  PRIMARY KEY (`id`),
 *  KEY `last_activity_for_gc` (`time`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
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
     * Construct the Db Session storage handler
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    protected function getTableName(): string
    {
        return $this->config['table'] ?? 'session';
    }

    /**
     * @return null|string
     */
    protected function getAdapterConnection(): ?string
    {
        return $this->config['adapter'] ?? null;
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
            throw new Exception('Table parameter is not defined in session\'s DB adapter options');
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
        try {
            Log::temporaryDisable('sql');
            $r = $this->getAdapter()->select($this->getTableName())->field('time')->field('data')->where('id', $sessionid)->fetchFirstObj();
            Log::restoreTemporaryDisablement();
            return $r;
        } catch (DbException $e) {
            Log::restoreTemporaryDisablement();
            throw $e;
        }
    }

    /**
     * @param string $sessionid
     *
     * @return string
     */
    public function read($sessionid)
    {
        $sess = $this->getDbData($sessionid);

        if ($sess === null) {
            return '';
        } else {
            return $sess->data;
        }
    }

    /**
     * @param string $sessionid
     * @param string $sessiondata
     *
     * @return bool
     */
    public function write($sessionid, $sessiondata)
    {
        $data = array(
          'time' => time(),
          'data' => $sessiondata
        );

        $sess = $this->getDbData($sessionid);

        if ($sess === null) {
            // the record doesn't exists in database, lets insert it
            $data['id'] = $sessionid;

            try {
                Log::temporaryDisable('sql');
                $this->getAdapter()->insert($this->getTableName(), $data)->exec();
                Log::restoreTemporaryDisablement();

                return true;
            } catch (KoldyDb\Exception $e) {
                Log::restoreTemporaryDisablement();
                Log::emergency($e);
                return false;
            }
        } else {
            // the record data already exists in db

            try {
                Log::temporaryDisable('sql');
                $this->getAdapter()->update($this->getTableName(), $data)->where('id', $sessionid)->exec();
                Log::restoreTemporaryDisablement();

                return true;
            } catch (KoldyDb\Exception $e) {
                Log::restoreTemporaryDisablement();
                Log::emergency($e);
                return false;
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
        try {
            Log::temporaryDisable('sql');
            $this->getAdapter()->delete($this->getTableName())->where('id', $sessionid)->exec();
            Log::restoreTemporaryDisablement();

            return true;
        } catch (KoldyDb\Exception $e) {
            Log::restoreTemporaryDisablement();
            Log::emergency($e);
            return false;
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

        try {
            Log::temporaryDisable('sql');
            $this->getAdapter()->delete($this->getTableName())->where('time', '<', $timestamp)->exec();
            Log::restoreTemporaryDisablement();

            return true;
        } catch (KoldyDb\Exception $e) {
            Log::restoreTemporaryDisablement();
            Log::emergency($e);
            return false;
        }
    }

}
