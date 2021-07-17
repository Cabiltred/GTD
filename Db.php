<?php

/**
 * Class conexion de bases de datos
 *
 * Esta clase permite hacer la conexion con la base de datos Mysql
 * y deja a disposicion la instancia para ser usada en toda la aplicacion.
 *
 *                                  ╔═╗      ╔═╗
 *                                ╔═╝ ╚═╗    ║ ║
 *                          ╔════╗╚╗  ╔═╝ ╔══╝ ║
 *                          ║ ╔╗ ║ ║  ║   ║ ╔╗ ║
 *                          ║ ╚╝ ║ ║  ╚═╗ ║ ╚╝ ║
 *                          ╚═╗  ║ ╚════╝ ╚════╝
 *                          ╔═╝  ║
 *                          ╚════╝
 *
 * PHP Version: 5.5
 *
 * Contribution:
 * Refactor code by Alberto Chaves Beltran  <manuel.chaves@softek.com>
 *
 * @category WebService
 * @package ClasesTransversal
 * @author Macarena Cerda Mora      <mcerda@grupogtd.com>
 * @author Franciso Sandoval Iturra <Francisco.Sandoval@grupogtd.com>
 * @copyright 2020 GTD
 * @license https://opensource.org/licenses/MIT MIT License
 * @link http://localhost/
 *
 */

namespace Gtd\ClasesTransversal;

// Se incluye el us de los namespaces necesarios para esta clase
use Gtd\ClasesTransversal\Config;
use Gtd\ClasesTransversal\Util;
use Gtd\ClasesTransversal\Mysql;

/**
 * Db
 */
class Db
{
    private $_config;       // Instancia variables de configuracion
    private $_util;         // Instancia de funciones utiles
    private $_dbName;       // Nombre de la base de datos
    private $_dbServer;     // Servidor de la base de datos
    private $_dbUser;       // Usuario de la base de datos
    private $_dbPass;       // Password de la base de datos
    private $_connection;    // Password de la base de datos
    private $_error;        // Retorno de error

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        // Se instancia la clase de configuración.
        $this->_config = new Config();
        // Se instancia la clase de funciones utiles.
        $this->_util = new Util();

        // En caso que no se envien parammetros de entrada se usan los
        // que se tienen configurados por defecto en congig
        $this->_dbName      = $this->_config->dbName;
        $this->_dbServer    = $this->_config->dbServer;
        $this->_dbUser      = $this->_config->dbUser;
        $this->_dbPass      = $this->_config->dbPass;

    }

    /**
     * _connect
     *
     * @param  mixed $dbName
     * @param  mixed $dbServer
     * @param  mixed $dbUser
     * @param  mixed $dbPass
     * @return void
     */
    private function _connect($dbName, $dbServer, $dbUser, $dbPass)
    {
        $return = TRUE;
        $this->_connection = new MySQL(
            TRUE,
            $dbName,
            $dbServer,
            $dbUser,
            $dbPass
        );
        if ($this->_connection->Error()) {
            $return = FALSE;
        }
        return $return;
    }

    /**
     * connectionMediador
     *
     * @return void
     */
    public function connectionMediador()
    {
        $return = TRUE;

        $connection = $this->_connect(
            $this->_config->dbName,
            $this->_config->dbServer,
            $this->_config->dbUser,
            $this->_config->dbPass
        );
        if ($connection === FALSE) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO HAY CONECCION CON DB MEDIAROR",
                "type"          => "DB_MEDIADOR"
            ];
            $error = $this->_util->setResponse($this, $data);
            $this->_error = $error;
            $return = FALSE;
        }
        return $return;
    }

    /**
     * connectionMinerva
     *
     * @return void
     */
    public function connectionMinerva()
    {
        $return = TRUE;

        $connection = $this->_connect(
            $this->_config->dbNameMinerva,
            $this->_config->dbServerMinerva,
            $this->_config->dbUserMinerva,
            $this->_config->dbPassMinerva
        );
        if ($connection === FALSE) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO HAY CONECCION CON DB MINERVA",
                "type"          => "DB_MINERVA"
            ];
            $error = $this->_util->setResponse($this, $data);
            $this->_error = $error;
            $return = FALSE;
        }
        return $return;
    }

    /**
     * getConnection
     *
     * @return void
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * getError
     *
     * @return void
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * setError
     *
     * @param  mixed $tipo
     * @param  mixed $detalle
     * @return void
     */
    public function setError($tipo, $detalle = "")
    {
        $error = [];
        switch ($tipo) {
            case "DB_MEDIADOR":
                // Genera estructura de retorno de error
                $str = "Error en la conexion de la base";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DB";
                $error["error"]["faultActor"]   = "MEDIADOR";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "DB_MINERVA":
                // Genera estructura de retorno de error
                $str = "Error en la conexion de la base";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DB";
                $error["error"]["faultActor"]   = "MINERVA";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
        }
        return $error;
    }


}
