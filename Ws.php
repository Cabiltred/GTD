<?php

/**
 * Class Vo
 *
 * Esta clase permite hacer todos los procesos de servicio contra el sistema
 * Minerva
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
use Gtd\ClasesTransversal\Sql;
use Gtd\ClasesTransversal\Util;
use Gtd\ClasesTransversal\Minerva;
use Aw\Nusoap\NusoapClient;
use TYPO3\PharStreamWrapper\Exception;

/**
 *
 * Ws
 */
class Ws
{

    public $respuesta;
    public $estadoMinerva;
    public $result;
    public $respCommand;
    public $urlLog;
    public $otherServices;
    private $_config;
    private $_dbMediador;   // Instancia para base de datos Mediador
    private $_dbMinerva;    // Instancia para base de datos Minerva
    private $_util;         // Instancia de funciones utiles
    private $_minerva;      // Instancia para funciones de Webservice Minerva
    public  $_sqlMediador;  // Instancia clase procesos Sql
    public  $_sqlMinerva;   // Instancia clase procesos Sql Minerva


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
        // Se instancia la clase de Ws de Minerva.
        $this->_minerva = new Minerva();

        $this->respuesta        = "";
        $this->estadoMinerva    = "";
        $this->result           = "";
        $this->respCommand      = "";
        $this->urlLog           = "";
        $this->urlLog           =  $this->_config->urlLog;
        $this->otherServices    =  array();
    }

    /**
     * consumeWs
     *
     * @param  mixed $nombre
     * @param  mixed $metodo
     * @param  mixed $parameters
     * @return void
     */
    public function consumeWs($nombre, $metodo, $parameters)
    {
        $proxyhost = "";
        $proxyport = "";
        $proxyUsername = "";
        $proxyPassword = "";
        $ws =  $this->_sqlMediador->getUrlWs($nombre);

        $data = [
            "ws"            => $nombre,
            "url"           => $ws,
            "metodo"        => $metodo,
            "parametros"    => $parameters
        ];
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "Ws Minerva:CONSUMO",
            "MEDIADOR",
            $data
        );

        if ($ws) {
            $client = new NusoapClient($ws, 'true');
            $client->soap_defencoding = 'UTF-8';
            $client->decode_utf8 = FALSE;
            $err = $client->getError();
            if ($err) {
                return FALSE;
            }
            $result = $client->call($metodo, $parameters, '', '', FALSE, TRUE);
            // Revisar por fallas
            if ($client->fault) {
                error_log($client->getError());
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "Ws Minerva:ERROR",
                    "MEDIADOR",
                    $client->getError()
                );
                return FALSE;
            } else { //recoger los resultados del webservice
                // Chequear por errores
                $err = $client->getError();

                if ($err) {
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "Ws Minerva:ERROR",
                        "MEDIADOR",
                        $client->getError()
                    );
                    return FALSE;
                } else {
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "Ws Minerva:RESPUESTA",
                        "MEDIADOR",
                        $result
                    );
                    return $result;
                }
            }
        } else {
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Ws Minerva:ERROR",
                "MEDIADOR",
                "No se encntro el WS para consumir"
            );
            return FALSE;
        }
    }

    /**
     * deleteStbMinerva
     *
     * @param  mixed $macs
     * @return void
     */
    public function deleteStbMinerva($macs)
    {
        $exitos = 0;
        foreach ($macs as $key => $value) {
            $parametersDel = array('cMacAddress' => $value);
            $resultDel = $this->consumeWS(
                "ELIMINA_STB",
                'TraeRegistros',
                $parametersDel
            );
            if ($resultDel[0]["estado"] == 0
             || $resultDel[0]["estado"] == 1002) {
                $exitos++;
            }
        }
        return $exitos;
    }

    /**
     * getStbs
     *
     * @param  mixed $instancia
     * @return void
     */
    public function getStbs($instancia)
    {
        $currentMacs = array();
        $prefijo        = $this->_config->prefix;
        $parameters = array(
            "ccustomerid" => $prefijo . $instancia
        );
        $result = $this->consumeWs(
            "INFO_N_SERVICIO_V2",
            "TraeRegistros",
            $parameters
        );

        if ($result) {
            if (is_array($result)) {
                $macsCurrent = $result[0]["cMac"];
                $macs = explode(";", $macsCurrent);
                foreach ($macs as $mac) {
                    $currentMacs[] = substr($mac, strpos($mac, "=") + 1);
                }
            }
        }
        return $currentMacs;
    }

    /**
     * getPlanesMinervaByCustomerId
     *
     * @param  mixed $customer_id
     * @return void
     */
    public function getPlanesMinervaByCustomerId($customer_id)
    {
        //planes actuales del cliente para Minerva
        $servicesPkgMinerva = array();
        $parameters = array('ccustomerid' => $customer_id);
        $result = $this->consumeWs("INFO_N_SERVICIO_V2",
                                   "TraeRegistros",
                                   $parameters);

        if (!empty($result[0]['cNameService'])) {
            if (($servicepkg = explode(";", $result[0]['cNameService']))
             && ($fservicepkg = explode(";", $result[0]['cCodeService']))) {
                foreach ($servicepkg as $num => $sp) {
                    if (strstr($sp, "=")) {
                        $code = $this->_sqlMediador->getCodMinervaByNameMinerva(
                            substr($sp,strpos($sp, "=") + 1)
                        );
                        if (!empty($code)) {
                            $servicesPkgMinerva[] = array(
                                "codigo" => $code,
                                "fecha_fin" => $fservicepkg[$num]);
                        } else {
                            $code =  $this->_sqlMediador->getServiceByName(
                                substr($sp, strpos($sp, "=") + 1)
                            );
                            $this->otherServices[] = array(
                                "codigo" => $code,
                                "fecha_fin" => $fservicepkg[$num]);
                        }
                    }
                }
            }
        } elseif (empty($result[0]['cCreateDate'])) {
            return -1;
        }
        return array_filter($servicesPkgMinerva);
    }

    /**
     * setChannelsPortal
     *
     * @param  mixed $instancia
     * @param  mixed $planesMinerva
     * @return void
     */
    public function setChannelsPortal($instancia, $planesMinerva)
    {
        $msj  = "setChannelsPortal consultar canales para planes=>";
        $msj .= $planesMinerva;
        error_log($msj);
        $planesMinerva = trim($planesMinerva, "|");
        $parameters = array("planes" => $planesMinerva);
        $result = $this->consumeWs(
            "WS-EXPERTO",
            "get_canales_contratados",
            $parameters
        );
        if (!empty($result)) {
            $canales = $result;
            error_log("CANALES OBTENIDOS=>" . $canales);
            $this->_sqlMinerva->saveChannelsPortal($canales, $instancia);
        } else {
            return FALSE;
        }
    }

    /**
     * setAlta
     *
     * @param  mixed $instancia
     * @param  mixed $planesMinerva
     * @return void
     */
    public function setAlta($instancia, $planesAlta)
    {
        $return = FALSE;
        $prefijo        = $this->_config->prefix;
        $parameters = array(
            "cCustomerId" => $prefijo . $instancia,
            "cChanelPkgAlta" => $planesAlta
        );
        $result = $this->consumeWs(
            "AGREGA_PLAN",
            "TraeRegistros",
            $parameters
        );

        if ($result) {
            if (is_array($result)) {
                if ($result[0]["estado"] == 0 || $result[0]["estado"] == 1002) {
                    $return = TRUE;
                }
            }
        }
        return $return;
    }


    /**
     * setBaja
     *
     * @param  mixed $instancia
     * @param  mixed $planesMinerva
     * @return void
     */
    public function setBaja($instancia, $planesBaja)
    {
        $return = FALSE;
        $prefijo        = $this->_config->prefix;
        $parameters = array(
            "cCustomerId" => $prefijo . $instancia,
            "cChanelPkgBaja" => $planesBaja
        );
        $result = $this->consumeWs(
            "ELIMINA_PLAN",
            "TraeRegistros",
            $parameters
        );

        if ($result) {
            if (is_array($result)) {
                if ($result[0]["estado"] == 0 || $result[0]["estado"] == 1002) {
                    $return = TRUE;
                }
            }
        }
        return $return;
    }


    /**
     * setOtherServices
     *
     * @param  mixed $instancia
     * @param  mixed $planesMinerva
     * @return void
     */
    public function setOtherServices($instancia, $planesOther)
    {

        $return = FALSE;
        $prefijo        = $this->_config->prefix;
        $parameters = array(
            "cCustomerId" => $prefijo . $instancia,
            "cChanelPkgAlta" => $planesOther
        );
        $result = $this->consumeWs(
            "AGREGA_PLAN",
            "TraeRegistros",
            $parameters);

        if ($result) {
            if (is_array($result)) {
                if ($result[0]["estado"] == 0 || $result[0]["estado"] == 1002) {
                    $return = TRUE;
                }
            }
        }
        return $return;
    }

    /**
     * setErrorMinerva
     *
     * @param  mixed $tipo
     * @param  mixed $detalle
     * @return void
     */
    public function setError($tipo, $detalle="")
    {
        $error = [];
        switch($tipo) {
            case "PLATAFORMA":
                // Genera estructura de retorno de error
                $str = "PLATAFORMA MINERVA NO ESTA ACTIVA EN MEDIADOR";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "CONFIGURACION";
                $error["error"]["faultActor"]   = "MEDIADOR";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "CONEXION":
                // Genera estructura de retorno de error
                $str = "PLATAFORMA TELSUR NO ESTA RESPONDIENDO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "CONEXION";
                $error["error"]["faultActor"]   = "WS MINERVA";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "RESPUESTA":
                // Genera estructura de retorno de error
                $str = "PLATAFORMA TELSUR ENTREGO UNA RESPUESTA NO VALIDA";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "RESPUESTA NO VALIDA";
                $error["error"]["faultActor"]   = "WS MINERVA";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "ESTADO":
                // Genera estructura de retorno de error
                $str = "PLATAFORMA TELSUR RETORNO UN ESTADO NO VALIDO";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "ESTADO NO VALIDO";
                $error["error"]["faultActor"]   = "WS MINERVA";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "DECO":
                // Genera estructura de retorno de error
                $str = "PLATAFORMA TELSUR NO PUDO ADICIONAR DECOS";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DECOS NO VALIDOS";
                $error["error"]["faultActor"]   = "WS MINERVA";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "REFRESCAR":
                // Genera estructura de retorno de error
                $str = "WEBSERVICE DE REFRESCAR_CAS NO SE EJECUTO CORRECTAMENTE";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "RESPUESTA NO VALIDA";
                $error["error"]["faultActor"]   = "WS MEDIADOR";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "OK":
                // Genera estructura de retorno de error VACIO
                $error["status"] = TRUE;
                $error["error"]["faultCode"]    = "";
                $error["error"]["faultActor"]   = "";
                $error["error"]["faultString"]  = "";
                $error["error"]["faultDetail"]  = "";
                break;
        }
        return $error;
    }

    /**
     * setInstanceDb
     *
     * @param  mixed $db
     * @return void
     */
    public function setInstanceDb($db)
    {
        if (!$this->_dbMediador) {
            $this->_dbMediador = $db;
        }
        $this->_sqlMediador = new Sql($this->_dbMediador);
    }

    /**
     * setInstanceDbMinerva
     *
     * @param  mixed $db
     * @return void
     */
    public function setInstanceDbMinerva($db)
    {
        if (!$this->_dbMinerva) {
            $this->_dbMinerva = $db;
        }
        $this->_sqlMinerva = new Sql($this->_dbMinerva);
    }

    /**
     * setStbMinerva
     *
     * @param  mixed $info
     * @return void
     */
    public function setStbMinerva($info, $addMac = TRUE)
    {
        $instancia      = $info["minerva"]["instancia"];
        $prefijo        = $this->_config->prefix;
        $subcuentas     = $info["minerva"]["subcuentas"];
        $macs           = $info["minerva"]["macs"];
        $series         = $info["minerva"]["series"];
        $tipoServicio   = $info["minerva"]["tipoServicio"];
        $parametros     = $info["minerva"]["parametros"];
        $currentMacs    = array();
        $exitosM = 0;
        try {
            // Obtioene los macs address actuales, aplica en modificacion
            if (isset($info["minerva"]["currentMacs"])) {
                $currentMacs    = $info["minerva"]["currentMacs"];
            }
            foreach ($series as $id => $serie) {
                $validaAdd = $this->validaAddStb($addMac, $macs[$id], $currentMacs);
                if($validaAdd == TRUE) {
                    $pvr = "N";
                    if(substr($serie, 0, 6) == "XV1512"
                    || substr($serie, 0, 2) == "G7") {
                        $pvr = "Y";
                    }

                    $streamCount = "1";
                    if (substr($serie, 0, 6) == "XV1512"
                    || substr($serie, 0, 2) == "G7") {
                        $streamCount = "2";
                    }

                    $paramAdd = array(
                        "cCustomerId"           => $prefijo . $instancia,
                        "cRegistrationNumber"   => "",
                        "cSerialNumber"         => $serie,
                        "cMacAddress"           => $macs[$id],
                        "IpAddress"             => "",
                        "cIpPort"               => "",
                        "cSwitchPort"           => "",
                        "cStbModel"             => "",
                        "cInitialStatus"        => 3,
                        "cStartDate"            => "",
                        "cStartTime"            => "",
                        "cEndDate"              => "",
                        "cEndTime"              => "",
                        "cSmartCardNum"         => "",
                        "cUseDHCP"              => "Y",
                        "cStreamCount"          => $streamCount,
                        "cHasHD"                => "Y",
                        "cHasPLT"               => "Y",
                        "cHasPVR"               => $pvr,
                        "cDevice_name"          => "STB" . intval($subcuentas[$id]),
                        "cPriority"             => intval($subcuentas[$id])
                    );

                    $resultAdd = $this->consumeWs("AGREGA_STB",
                                                "TraeRegistros",
                                                $paramAdd);
                    error_log("Agregando " . $serie . "=>" . $resultAdd[0]["estado"]);

                    if (is_array($resultAdd)
                    && ($resultAdd[0]["estado"] == 0
                    || $resultAdd[0]["estado"] == 1002)) {
                        $exitosM++;
                        if (strstr($tipoServicio, "IPTV")) {
                            $this->_minerva->logIn();
                            $mac = $macs[$id];
                            $skin = $this->_minerva->setSkinforDevice($mac);
                            $skin = FALSE;
                            if (!$skin) {
                                $output = "FALLA SET_SKIN";
                                $input = implode("_", $parametros);
                                $this->_sqlMediador->setError("MINERVA",
                                                $input,
                                                $output,
                                                "ALTA_SERVICIO");
                            }
                            $this->_minerva->logOut();
                        }
                    } elseif ($resultAdd[0]["estado"] == -3) {
                        // si falla (error -3)
                        $msj  = "Borrando " . $macs[$id];
                        $msj .="=>" . $resultAdd[0]["estado"];
                        error_log($msj);
                        // es porque la mac ya existe, entonces
                        // lo borro y agrego de nuevo
                        $paramDel = array('cMacAddress' => $macs[$id]);
                        $resultDel = $this->consumeWs("ELIMINA_STB",
                                                    "TraeRegistros",
                                                    $paramDel);
                        error_log("Resultado del borrado " . $resultDel[0]["estado"]);
                        if ($resultDel[0]["estado"] == 0
                        || $resultDel[0]["estado"] == 1002) {
                            $msj = "Borrado " . $serie . "=>" . $resultDel[0]["estado"];
                            error_log($msj);
                            $resultAdd = $this->consumeWs("AGREGA_STB",
                                                        "TraeRegistros",
                                                        $paramAdd);
                            $msj  = "Agregando " . $serie;
                            $msj .="=>" . $resultAdd[0]["estado"];
                            error_log($msj);
                            if ($resultAdd[0]["estado"] == 0
                            || $resultAdd[0]["estado"] == 1002) {
                                $exitosM++;
                                if (strstr($tipoServicio, "IPTV")) {
                                    $this->_minerva->logIn();
                                    $mac = $macs[$id];
                                    $skin = $this->_minerva->setSkinforDevice($mac);
                                    $skin = FALSE;
                                    if (!$skin) {
                                        $output = "FALLA SET_SKIN";
                                        $input = implode("_", $parametros);
                                        $this->_sqlMediador->setError("MINERVA",
                                                        $input,
                                                        $output,
                                                        "ALTA_SERVICIO");
                                    }
                                    $this->_minerva->logOut();
                                }
                            }
                        }
                    }
                }
            }
        }catch (Exception $e){
            $exitosM = 0;
        }
        return $exitosM;
    }

    /**
     * setStbMinervaDelete
     *
     * @param  mixed $info
     * @return void
     */
    public function setStbMinervaAdicional($info)
    {
        $return = array();
        $instanciaRutSubcuenta  = $info["minerva"]["instanciaRutSubCuenta"];
        $instancia              = $info["minerva"]["instancia"];
        $macs                   = $info["minerva"]["macs"];
        $planes                 = $info["minerva"]["planesOriginal"];
        $dvr                    = $info["minerva"]["dvr"];
        $servicios              = $info["minerva"]["servicios"];
        $currentMacs    = array();
        try {

            // Obtioene los macs address actuales, aplica en modificacion
            if (isset($info["minerva"]["currentMacs"])) {
                $currentMacs    = $info["minerva"]["currentMacs"];
            }

            // si la mac q esta en minerva no pertenece al cliente se elimina
            foreach ($currentMacs as $id=>$mac) {
                if (!in_array($mac, $macs) && $mac != "") {
                    $parametersDel = array(
                        'cMacAddress' => $mac
                    );
                    $resultDel = $this->consumeWs(
                        "ELIMINA_STB",
                        'TraeRegistros',
                        $parametersDel
                    );
                    if($resultDel){
                        if ($resultDel[0]["estado"] == 0){
                            $return["elimina_plan"] = TRUE;
                        }
                    }
                }
            }

            // Se hace refresco de planes
            $parameters2 = array(
                "instanciaRutSubcuenta" => $instanciaRutSubcuenta,
                "planes" => $planes,
                "macs" => implode("|",$macs),
            );
            $result = $this->consumeWs(
                "REFRESCAR_CAS",
                "ws_tv_refrescar_cas",
                $parameters2);
            if ($result) {
                if ($result["estado_minerva"] == "OK") {
                    $return["refresca_cas"] = TRUE;
                }else{
                    $return["refresca_cas"] = FALSE;
                }
            }

            // Se adicionan servicios adicionales
            if ($dvr) {
                $parametersAgregar = array(
                    "cCustomerId" => "GTD" . $instancia,
                    "cChanelPkgAlta" => $servicios
                );
                $resultA = $this->consumeWs("AGREGA_PLAN", "TraeRegistros", $parametersAgregar);
                if ($resultA) {
                    if (is_array($resultA)) {
                        if ($resultA[0]["estado"] == 0){
                            $return["agrega_plan"] = TRUE;
                        }
                    }
                }

            }

        } catch (Exception $e) {
            $return["status"] = FALSE;
        }
        return $return;
    }

    /**
     * validaAddStb
     *
     * @param  mixed $addMac
     * @param  mixed $mac
     * @param  mixed $currentMacs
     * @return void
     */
    public function validaAddStb($addMac, $mac, $currentMacs)
    {
        $return = FALSE;
        if($addMac == TRUE){
            $return = TRUE;
        }else{
            if(!in_array($mac, $currentMacs) && $mac != ""){
                $return = TRUE;
            }
        }
        return $return;
    }

    /**
     * validarWidget
     *
     * @param  mixed $codigos
     * @return void
     */
    public function validarWidget($codes)
    {
        $return = FALSE;
        foreach ($codes as $code) {
            $result = $this->_sqlMediador->getServiceWidget($code);
            if($result === TRUE){
                $return = TRUE;
            }
        }
        return $return;
    }

}
