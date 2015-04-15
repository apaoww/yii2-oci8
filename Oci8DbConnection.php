<?php
namespace apaoww\oci8;
require_once('pdo/Oci8PDO.php');

use yii;
use yii\db\Connection;

class Oci8DbConnection extends Connection
{

    public $pdoClass = 'Oci8PDO';
    public $schemaMap = ['oci8'=>'apaoww\oci8\Schema'];




    /**
     * Creates the PDO instance.
     * When some functionalities are missing in the pdo driver, we may use
     * an adapter class to provides them.
     * @return PDO the pdo instance
     * @throws $e
     */
    protected function createPdoInstance()
    {
        if (!empty($this->charset)) {
            Yii::trace('Error: OciDbConnection::$charset has been set to `' . $this->charset . '` in your config. The property is only used for MySQL and PostgreSQL databases. If you want to set the charset in Oracle to UTF8, add the following to the end of your OciDbConnection::$connectionString: ;charset=AL32UTF8;', 'ext.oci8Pdo.OciDbConnection');
        }

        try {
            Yii::trace('Opening Oracle connection', 'ext.oci8Pdo.OciDbConnection');
            $pdoClass = parent::createPdoInstance();
        } catch (PDOException $e) {
            throw $e;
        }
        return $pdoClass;
    }

    /**
     * Closes the currently active Oracle DB connection.
     * It does nothing if the connection is already closed.
     */
    public function close()
    {
        Yii::trace('Closing Oracle connection', 'ext.oci8Pdo.OciDbConnection');
        parent::close();
    }

}