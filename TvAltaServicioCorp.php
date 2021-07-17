<?php

/**
 * Class Alta de Servicio de Televisión
 *
 * Esta clase permite realizar el proceso de Alta de Servicio para Minerva y VO
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
 * @package ClasesWs
 * @author Macarena Cerda Mora      <mcerda@grupogtd.com>
 * @author Franciso Sandoval Iturra <Francisco.Sandoval@grupogtd.com>
 * @copyright 2020 GTD
 * @license https://opensource.org/licenses/MIT MIT License
 * @link http://localhost/
 *
 */

namespace Gtd\ClasesWs;

// Se incluye el us de los namespaces necesarios para esta clase
use Gtd\ClasesTransversal\Config;
use Gtd\ClasesTransversal\Util;
use Gtd\ClasesTransversal\Db;
use Gtd\ClasesTransversal\Sql;
use Gtd\ClasesTransversal\Ws;
use Gtd\ClasesTransversal\Vo;

/**
 *
 * TvAltaServicioCorp
 */
class TvAltaServicioCorp
{
    private $_instancia;
    private $_planes;
    private $_servicios;
    private $_nombre;
    private $_apellido;
    private $_direccion;
    private $_ciudad;
    private $_tipoServicio;
    private $_serviceId;
    private $_serviceOrder;
    private $_rut;
    private $_info;         // Datos originales
    private $_aux;          // Datos procesados
    private $_idConsulta;   // Almacena el registro de la consulta de a Db
    private $_return;       // Estructura de retorno
    private $_config;       // Instancia variables de configuracion
    private $_util;         // Instancia de funciones utiles
    private $_dbMediador;   // Instancia para base de datos Mediador
    private $_dbMinerva;    // Instancia para base de datos Minerva
    private $_sqlMediador;  // Instancia clase procesos Sql Mediador
    private $_sqlMinerva;   // Instancia clase procesos Sql Minerva

    /**
     * __construct
     *
     * @param  mixed $info
     * @return void
     */
    public function __construct($info)
    {
        $this->_instancia  = $info["instancia"];
        $this->_planes              = $info["planes"];
        $this->_servicios           = $info["servicios"];
        $this->_nombre              = $info["nombre"];
        $this->_apellido            = $info["apellido"];
        $this->_direccion           = $info["direccion"];
        $this->_ciudad              = $info["ciudad"];
        $this->_tipoServicio        = $info["tipoServicio"];
        $this->_serviceId           = $info["serviceId"];
        $this->_serviceOrder        = $info["serviceOrder"];
        $this->_rut                 = $info["rut"];
        $this->_info                = $info;
        $this->_aux                 = [];
        $this->_dbMediador          = FALSE;
        $this->_dbMinerva           = FALSE;

        // Se instancia la clase de configuración.
        $this->_config = new Config();
        // Se instancia la clase de funciones utiles.
        $this->_util = new Util();

        // Se construye la estructura de retorno a utlizar, inicia vacia
        $this->_return = array (
                        "default" => array(
                                    "status"  => "",
                                    "data"    => "",
                                    "message" => "",
                                    "error"   => array("faultCode"   => "",
                                                    "faultActor"  => "",
                                                    "faultString" => "",
                                                    "faultDetail" => "",
                                                    "Default"     => ""),
                                    )
                            );

    }

    /**
     * altaServicio
     *
     * Esta función genera el Alta de servicio para las plataformas definidas
     *
     * @return struct
     */
    public function altaServicio()
    {
        $return = [];
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "A L T A   D E   S E R V I C I O   C O R P",
            "MEDIADOR",
            "Inicia aprovisionamiento de servicio en Minerva y Vo",
            "INICIO",
        );
        // Se obtiene la conexion con la base de datos Mediador
        $validaDb        = $this->_validaConexionDb();
        // Se obtiene la conexion con la base de datos Minerva
        $validaDbMinerva = $this->_validaConexionDbMinerva();
        // Se evalua que las dos conexiones a la base de datos sea correcta
        if ($validaDb && $validaDbMinerva) {
            // Se registra transaccion al iniciar proceso
            $this->_idConsulta = $this->_util->startTransaction(
                "alta_servicio_corp",
                $this->_sqlMediador,
                $this->_info,
            );
            // Se validan los parametros de entrada
            $validaParametros = $this->_validaParametros();
            // Se valida si los parametros traen la información correcta
            if ($validaParametros === TRUE) {
                // Se invoca el proceso que da de alta un servicio en Minerva
                $return = $this->_altaServicioMinerva();
                $this->_return["default"] = $return["default"];
                $this->_return["minerva"] = $return["minerva"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }
                // Se invoca el proceso que da de alta un servicio en VO
                $return = $this->_altaServicioVO();
                $this->_return["default"] = $return["default"];
                $this->_return["vo"]      = $return["vo"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }
                // Se evalua si ocurrio alguna excepcion
                if ($this->_return["default"]["status"] !== FALSE) {
                    // Se registra transaccion al finalizar proceso
                    $this->_idConsulta = $this->_util->endTransaction(
                        "respuesta_alta_servicio_corp",
                        $this->_sqlMediador,
                        $this->_return,
                        $this->_idConsulta
                    );
                    // Obtiene la respuesta definitiva a retornar en el WS
                    $respuesta = $this->_util->getResponseWs(
                        $this->_return
                    );
                    $this->_return["default"]["respuesta"] = $respuesta;
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "A L T A   D E   S E R V I C I O   C O R P",
                        "MEDIADOR",
                        "Finaliza aprovisionamiento servicio en Minerva y Vo",
                        "FIN",
                    );
                }
            }
        }
        return $this->_return;
    }

    /**
     * _altaServicioMinerva
     *
     * Esta función genera el Alta de servicio en la plataforma Minerma
     *
     * @return void
     */
    private function _altaServicioMinerva()
    {
        $return = [];
        $ws = new Ws();
        // Se envia la instancia de la base de datos del mediador a Minerva
        $ws->setInstanceDb($this->_dbMediador);
        // Se envia la instancia de la base de datos de minerva a Minerva
        $ws->setInstanceDbMinerva($this->_dbMinerva);
        // Verifica que la plataforma Minerva este activa para aprovisionamiento
        $rsPlataforma = $this->_sqlMediador->getPlatform("Minerva");
        // Verifica que la plataforma Vo este activa para aprovisionamiento
        if ($rsPlataforma === TRUE) {
            // Obtiene la Url del Ws a ser invocado
            $urlWs =  $this->_sqlMediador->getUrlWs("ALTA_SERVICIO");
            $rsVAlidaUrl = $this->_util->validateUrl($urlWs);
            // Valida si la URL del WS de Minerva esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Prepara los datos para Aprovisionamiento de Minerva
                $validaPrepara = $this->_prepararDatosMinerva();
                if ($validaPrepara) {
                    $validaWidget =$this->_validaWidgets($ws);
                    // Se valida si los servicios tienen widget asociado
                    if ($validaWidget === TRUE) {
                        // Procesa Alta Minerva
                        // La respuesta del proceso de alta es alamcena en
                        // $this->_return tanto la respuesta del proceso como la
                        // respuesta del error, en caso de existir
                        $return = $this->setAltaServicioMinerva($ws, $this->_aux);
                    } else {
                        // informacion para construir log de respuesta y error
                        $data = [
                            "messageLog"    => "SERVICIOS SIN WIDGET ASOCIADO",
                            "type"          => "URL",
                            "platform"      => "MINERVA",
                            "state"         => FALSE,
                            "detail"        => "",
                            "result"        => [
                                "estado" => "SERVICIOS SIN WIDGET ASOCIADO",
                                "estado_desc" => "NOOK"
                            ]
                        ];
                        $return = $this->_util->setResponse($ws, $data);
                        // Almacena Log de seguimiento
                        $this->_util->setLog(
                            $this->_config->urlLog,
                            "Servicios Widget:ERROR",
                            "MEDIADOR",
                            $data
                        );
                    }
                }
            } else {
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "URL WS MINERVA NO RESPONDE",
                    "type"          => "URL",
                    "platform"      => "MINERVA",
                    "state"         => FALSE,
                    "detail"        => "",
                    "result"        => [
                        "estado" => "URL WS MINERVA NO RESPONDE",
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($ws, $data);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "WS Minerva:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
        }else{
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "NO ACTIVO MINERVA",
                "type"          => "PLATAFORMA",
                "platform"      => "MINERVA",
                "state"         => FALSE,
                "detail"        => "",
                "result"        => [
                    "estado"=>"NO ACTIVO MINERVA",
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($ws, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Plataforma Minerva:INACTIVA",
                "MEDIADOR"
            );
        }
        return $return;
    }

    /**
     * _altaServicioVo
     *
     * Esta función genera el Alta de servicio en la plataforma VO
     *
     * @return strunct
     */
    private function _altaServicioVo()
    {
        $return = [];
        $vo = new Vo();
        // Se envia la instancia de la base de datos del mediador a VO
        $vo->setInstanceDb($this->_dbMediador);
        $rsPlataforma = $this->_sqlMediador->getPlatform("Vo");
        // Verifica que la plataforma Vo este activa para aprovisionamiento
        if ($rsPlataforma === TRUE) {
            $rsVAlidaUrl = $this->_util->validateUrl($this->_config->urlVo);
            // Valida si la URL del WS de VO esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Obtiene el token para conexxion con el WS
                $rsToken = $vo->setToken();
                // Valida que el token se haya generado
                if($rsToken === TRUE) {
                    // Prepara los datos para Aprovisionamiento de VO
                    $validaPrepara = $this->_prepararDatosVo();
                    if ($validaPrepara) {
                        // Procesa Alta VO
                        $return = $this->_setAltaServicioVo(
                            $vo,
                            $this->_aux
                        );
                    }
                } else {
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "TOKEN VO NO SE PUDO GENERAR",
                        "type"          => "TOKEN",
                        "platform"      => "VO",
                        "state"         => FALSE,
                        "detail"        => "",
                        "result"        => [
                            "estado" => "TOKEN VO NO SE PUDO GENERAR",
                            "estado_desc" => "NOOK"
                        ]
                    ];
                    $return = $this->_util->setResponse($vo, $data);
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "API TOKEN VO:ERROR",
                        "MEDIADOR",
                        $data
                    );
                }
            }else{
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "URL WS VO NO RESPONDE",
                    "type"          => "URL",
                    "platform"      => "VO",
                    "state"         => FALSE,
                    "detail"        => "",
                    "result"        => [
                        "estado" => "URL WS VO NO RESPONDE",
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($vo, $data);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "API VO:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
        }else {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "NO ACTIVO VO",
                "type"          => "PLATAFORMA",
                "platform"      => "VO",
                "state"         => FALSE,
                "detail"        => "",
                "result"        => [
                    "estado" => "NO ACTIVO VO",
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Plataforma VO:INACTIVA",
                "MEDIADOR"
            );
        }
        return  $return;
    }

    /**
     * getDescripcionEstado
     *
     * @param  mixed $codigo
     * @return void
     */
    public function getDescripcionEstado($codigo)
    {
        $str = "";
        switch ($codigo) {
            case "-2":
                // Genera mensaje de retorno de error
                $str = $codigo . " - ERROR GENERAL";
                break;
            case "-3":
                // Genera mensaje de retorno de error
                $str = $codigo . " - DATA REQUERIDA NO FUE ENCONTRADA";
                break;
            case "-4":
                // Genera mensaje de retorno de error
                $str = $codigo . " - FORMATO FECHA NO VALIDO";
                break;
            case "-6":
                // Genera mensaje de retorno de error
                $str = $codigo . " - INFORMACION DE CLIENTE NO FUE ADICIONADA";
                break;
            case "-11":
                // Genera mensaje de retorno de error
                $str = $codigo . " - CLIENTE YA EXISTE";
                break;
            case "-12":
                // Genera mensaje de retorno de error
                $str = $codigo . " - INFORMACION DE CLIENTE NO FUE ADICIONADA";
                break;
            case "-14":
                // Genera mensaje de retorno de error
                $str = $codigo . " - ID DE SERVICIO NO VALIDO";
                break;
            case "-15":
                // Genera mensaje de retorno de error
                $str = $codigo . " - REGION NO VALIDA";
                break;
            case "-27":
                // Genera mensaje de retorno de error
                $str = $codigo . " - CLIENTE POSEE MAS DE 64 PLANES";
                break;
            case "-95":
                // Genera mensaje de retorno de error
                $str = $codigo . " - INSTANCIA POSEE MAS DE 20 CARACTERES";
                break;
            case "-100":
                // Genera mensaje de retorno de error
                $str = $codigo . " - EXCEPCION DE SERVICIO";
                break;
            default:
                $str = $codigo . " - ERROR NO CODIFICADO";
        }
        return $str;
    }

    /**
     * _prepararDatosMinerva
     *
     * Esta función prepara los datos previos al consumo del WS de la
     * la plataforma Minerva
     *
     * @return struct
     */
    private function _prepararDatosMinerva()
    {
        $retorno = FALSE;

        // Fecha hoy
        $dateToday = date("d/m/Y");

        // Prepara Instancia
        $instancia  = $this->_instancia;
        $this->_aux["minerva"]["instancia"] = $instancia;

        // Prepara Direccion
        $direccion = str_replace(",", " ", $this->_direccion);
        $this->_aux["minerva"]["direccion"]     = $direccion;

        // Prepara Ciudad
        $ciudad = str_replace(",", " ", $this->_ciudad);
        $this->_aux["minerva"]["ciudad"] = $ciudad;

        // Prepara tipoServicio
        $tipoServicio = $this->_tipoServicio;
        $this->_aux["minerva"]["tipoServicio"] = $tipoServicio;

        // Prepara stbs
        $stbs= 1;
        $this->_aux["minerva"]["stbs"] = $stbs;

        // Prepara login
        $login = $instancia;
        $this->_aux["minerva"]["login"] = $login;

        // Prepara Email
        $email = $instancia. $this->_config->dominioMinerva;
        $this->_aux["minerva"]["email"] = $email;

        // Prepara planes
        $resultPlanes = $this->_preparaPlanes($this->_planes);
        $planes         = $resultPlanes["planes"];
        $numeroPlanes   =  $resultPlanes["numeroPlanes"];
        $this->_aux["minerva"]["planes"] = $planes;
        $this->_aux["minerva"]["numeroPlanes"] = $numeroPlanes;

        // Prepara servicios
        $serviciosNew = $this->_util->parseData($this->_servicios, ",");
        $strServiciosNew = $serviciosNew[2];
        $vecServiciosNew = $this->_util->parseData($strServiciosNew, "|");
        $numeroServiciosNew = "";
        $pM = "";
        if (strstr($strServiciosNew, "|")
        || (!strstr($strServiciosNew, "/") && $strServiciosNew != "''")) {
            foreach ($vecServiciosNew as $servicio) {
                if ($servicio != "''") {
                    $codServicio = $this->_sqlMediador->getServiceByNcId($servicio);
                    $pM .= $codServicio . "|";
                    $numeroServiciosNew .= $codServicio . "|";
                }
            }
            $serviciosNew[2] = trim($pM, "|");
        }
        $serviciosNew = implode(",", $serviciosNew);
        $this->_aux["minerva"]["serviciosNew"] = $serviciosNew;
        $this->_aux["minerva"]["numeroServiciosNew"] = $numeroServiciosNew;

        // Prepara email
        $dominio = $this->_config->dominioMinerva;
        $email = $this->_aux["minerva"]["instancia"] . $dominio;
        $this->_aux["minerva"]["email"] = $email;

        // Prepara servicios
        $servicios  = "";
        $servicios  = ",'" . $dateToday . "','',";
        $servicios .= $this->_sqlMediador->getServiceByName("INTERNET") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("HD") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("CALLERID") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("Remote Scheduling");
        // Se concatenan los nuevos servicios
        $servicios .= "," . $serviciosNew;
        $this->_aux["minerva"]["servicios"] = $servicios;

        //Prepara region
        if (!strstr($tipoServicio, "EMPRESA")) {
            //cliente residencial
            //por defecto region RF (993)
            $region = "RM";
            if (strstr($tipoServicio, "IPTV")) {
                //region unica para IPTV (853)
                $region = "IPTV_RESIDENCIAL_STGO";
            }
        } else {
            //corporativo
            $pM = $this->_aux["minerva"]["numeroPlanes"];
            $iptv = $this->_sqlMediador->getRegByPlan(trim($pM, "|"));
            $region = $iptv['REGION'];
        }

        // Se obtiene el codigo de la región
        $codigoRegion = $this->_sqlMediador->getRegion($region);
        $this->_aux["minerva"]["region"] = $region;
        $this->_aux["minerva"]["codigoRegion"] = $codigoRegion;

        // Prepara estructura de consumo de WS para aprovisionamiento
        // del servicio de alta en Minerva
        $parametros = array("cFirstName" => utf8_encode($this->_nombre),
                            "cLastName" => utf8_encode($this->_apellido),
                            "cStreet" => utf8_encode($this->_direccion),
                            "cCity" => utf8_encode($this->_ciudad),
                            "cStbCount" => $stbs,
                            "cLogin" => $login,
                            "cPassword" => "123",
                            "cPin" => "123",
                            "cChanelPkg32v2" => $planes . $servicios,
                            "cCustomerId" => $instancia,
                            "cEnableStreamManagment" => "N",
                            "cTotlaAttainableBandwidth" => "100000",
                            "cRegId" => $codigoRegion,
                            "crs_login" => $instancia,
                            "crs_password" => "123",
                            "crs_email" => $email);
        $this->_aux["minerva"]["parametros"] = $parametros;
        $retorno = TRUE;

        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "Preparacion de datos Minerva:OK",
            "DATOS",
            $this->_aux["minerva"]
        );
        return $retorno;
    }

    /**
     * _prepararDatosVo
     *
     * Esta función prepara los datos previos al consumo del WS de la
     * plataforma VO
     *
     * @return struct
     */
    private function _prepararDatosVo()
    {
        $retorno = FALSE;
        // Prepara Instancia
        $instancia  = $this->_instancia;
        $this->_aux["minerva"]["instancia"] = $instancia;

        // Prepara rut
        $rut = $this->_rut;
        $this->_aux["vo"]["rut"]     = $rut;

        // Prepara servicecode
        $this->_aux["vo"]["serviceCode"] = $instancia;

        // Prepara name
        $name = $this->_nombre . " " . $this->_apellido;
        $this->_aux["vo"]["name"]     = $name;

        // Prepara address
        $address = $this->_direccion;
        $this->_aux["vo"]["address"]     = $address;

        // Prepara phone
        $phone = "";
        $this->_aux["vo"]["phone"]     = $phone;

        // Prepara comment
        $comment = "Creado desde Mediador";
        $this->_aux["vo"]["comment"]     = $comment;

        // Prepara stbs
        $stbs = 1;
        $this->_aux["vo"]["stbs"] = $stbs;

        // Prepara planes
        $planes = $this->_util->parseData($this->_planes, ",");
        // Se carga la posicion 2, la cual es la que trae los planes
        $strPlanes = $planes[2];
        $vecPlanes = $this->_util->parseData($strPlanes, "|");
        $planesVo = [];
        foreach ($vecPlanes as $key => $value) {
            $planesVo[] = $value;
        }
        $this->_aux["vo"]["planes"]     = $planesVo;

        //Prepara region
        $tipoServicio = $this->_tipoServicio;
        if (!strstr($tipoServicio, "EMPRESA")) {
            //cliente residencial
            //por defecto region RF (993)
            $region = "RM";
            if (strstr($tipoServicio, "IPTV")) {
                //region unica para IPTV (853)
                $region = "IPTV_RESIDENCIAL_STGO";
            }
        } else {
            //corporativo
            // Evaluar si este dato se debe sacar de esta validacion, ya que
            // para inerva funciona perfecto, pero paga VO este dato no deberia
            // aplicar
            $pM = $this->_aux["minerva"]["numeroPlanes"];
            $iptv = $this->_sqlMediador->getRegByPlan(trim($pM, "|"));
            $region = $iptv['REGION'];
        }
        // Se obtiene el codigo de la región
        $codigoRegion = $this->_sqlMediador->getRegion($region);
        $this->_aux["vo"]["region"] = $region;
        $this->_aux["vo"]["codigoRegion"] = $codigoRegion;

        // Prepara serviceId
        $serviceId = $this->_serviceId;
        $vecServId = $this->_util->parseData($serviceId, "-");
        $this->_aux["vo"]["serviceId"]    = $serviceId;

        // Prepara Puerto
        /*$puerto  = $vecServId[3] . "-";
        $puerto .= $vecServId[4] . "-";
        $puerto .= $vecServId[5] . "-";
        $puerto .= $vecServId[6];*/
        $this->_aux["vo"]["ftthPort"]    = "";

        // Prepara Nodo
        /*$nodo  = $vecServId[1] . "-";
        $nodo .= $vecServId[2];*/
        $this->_aux["vo"]["ftthNode"]    = "";

        // Prepara serviceCode
        $serviceOrder = $this->_serviceOrder;
        $this->_aux["vo"]["serviceOrder"]  = $serviceOrder;

        // Prepara ftthEquipo
        // Se envia vacio ya que se definio que no sera necesario
        $this->_aux["vo"]["ftthEquipo"]  = "";

        $retorno = TRUE;
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "Preparacion de datos VO:OK",
            "DATOS",
            $this->_aux["vo"]
        );
        return $retorno;
    }

    private function _preparaPlanes($planes)
    {
        $retorno = array();
        // Prepara planes
        $planes1 = $this->_util->parseData($planes, ",");
        $strPlanes = $planes1[2];
        $vecPlanes = $this->_util->parseData($strPlanes, "|");
        $numeroPlanes = "";
        $pM = "";
        if (
            strstr($strPlanes, "|")
            || (strlen($strPlanes) <= 4 && $strPlanes != "''")
            || $strPlanes == "IPTV Santiago"
        ) {
            foreach ($vecPlanes as $plan) {
                if ($plan != "''") {
                    $codPlan = $this->_sqlMediador->getCodeMinerva($plan);
                    $pM .= $codPlan . "|";
                    $numeroPlanes .= $codPlan . "|";
                }
            }
            $planes1[2] = trim($pM, "|");
        }
        $planes1 = implode(",", $planes1);
        $retorno["planes"] = $planes1;
        $retorno["numeroPlanes"] = $numeroPlanes;
        return $retorno;
    }

    /**
     * setAltaServicioMinerva
     *
     * @param  mixed $ws
     * @param  mixed $info
     * @return void
     */
    public function setAltaServicioMinerva($ws, $info)
    {
        $return         = [];
        $objSql         = $this->_sqlMediador;
        $parametros     = $info["minerva"]["parametros"];
        $instancia      = $info["minerva"]["instancia"];
        $numeroPlanes   = $info["minerva"]["numeroPlanes"];
        // Consumo del servicio de Aprovisionamiento de Minerva
        $result = $ws->consumeWs(
            "ALTA_SERVICIO",
            "TraeRegistros",
            $parametros
        );
        // Valida el consumo del WS
        if ($result) {
            error_log("provisionando en Minerva");
            if (is_array($result)) {
                error_log("conexion alta_servicio");
                if ($result[0]["estado"] == 0 || $result[0]["estado"] == 1002) {
                    // Aprovisionamiento exitoso, proceso a llenar DB con planes
                    // disponibles para esta instancia, ocupado por portal
                    $ws->setChannelsPortal(
                        $instancia,
                        $numeroPlanes
                    );
                    $estado = "APROVISIONADO CORRECTAMENTE";
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "OK:PROCESO CORRECTO",
                        "type"          => "OK",
                        "platform"      => "MINERVA",
                        "state"         => TRUE,
                        "detail"        => "",
                        "input"         => implode("_", $parametros),
                        "output"        => $estado,
                        "ws"            => "ALTA_SERVICIO",
                        "result"        => [
                            "estado" => $estado,
                            "estado_desc" => "OK"
                        ]
                    ];
                    $return = $this->_util->setResponse(
                        $ws,
                        $data,$objSql
                    );
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "Alta Servicio Minerva:OK",
                        "MEDIADOR"
                    );

                } else {
                    $detail = $this->getDescripcionEstado($result[0]["estado"]);
                    $estado = "WS ALTA EL ESTADO DE RESPUESTA NO ES CORRECTO: ";
                    $estado .= $detail;
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "ERROR: WS ALTA ESTADO NO CORRECTO",
                        "type"          => "ESTADO",
                        "platform"      => "MINERVA",
                        "state"         => FALSE,
                        "detail"        => $detail,
                        "input"         => implode("_", $parametros),
                        "output"        => $estado,
                        "ws"            => "ALTA_SERVICIO",
                        "result"        => [
                            "estado" => $estado,
                            "estado_desc" => "NOOK"
                        ]
                    ];
                    $return = $this->_util->setResponse($ws, $data, $objSql);
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "WS Minerva Estado:ERROR",
                        "MEDIADOR",
                        $data
                    );
                }
                error_log("Resultado de alta_servicio" . $estado);
            } else {
                $estado = "RESPUESTA DE WS ALTA NO ES CORRECTA";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "ERROR: WS ALTA RESPONDE INCORRECTO",
                    "type"          => "RESPUESTA",
                    "platform"      => "MINERVA",
                    "state"         => FALSE,
                    "detail"        => "",
                    "input"         => implode("_", $parametros),
                    "output"        => $estado,
                    "ws"            => "ALTA_SERVICIO",
                    "result"        => [
                        "estado" => $estado,
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($ws, $data, $objSql);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "WS Minerva Respuesta:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
            error_Log("estadoMinerva" . $estado);
        } else {
            $estado = "SIN CONEXION";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: FALLO CONSUMO WS ALTA DE SERVICIO",
                "type"          => "CONEXION",
                "platform"      => "MINERVA",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => implode("_", $parametros),
                "output"        => $estado,
                "ws"            => "ALTA_SERVICIO",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($ws, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "WS Minerva:ERROR",
                "MEDIADOR",
                $data
            );
        }
        return $return;
    }

    /**
     * _setAltaServicioVo
     *
     * @param  mixed $info
     * @param  mixed $info
     * @return void
     */
    private function _setAltaServicioVo($vo, $info)
    {
        $return   = [];
        $objSql = $this->_sqlMediador;

        // Se valida que los planes que se enviaron esten activos y disponibles
        // todos los planes deben validarse como TRUE, si al menos uno de los
        // planes enviados no esta bien, el proceso se desecha
        $rsPlan = $vo->validatePlanes($info);
        if ($rsPlan === TRUE) {
            // Se verifica que el cliente exista, en caso que no exista se
            // envia a crear, solo si el proceso retorna TRUE se continua
            $rsCliente = $vo->setCliente($info);
            if ($rsCliente === TRUE) {
                // Se verifica que el servicio exista, en caso que no exista se
                // envia a crear, solo si el proceso retorna TRUE se continua
                $rsServicio = $vo->setServicioCorp($info);
                if ($rsServicio === TRUE) {
                    //Respuesta OK
                    $estado = "APROVISIONADO CORRECTAMENTE";
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog" => "OK: PROCESO CORRECTO",
                        "type"       => "OK",
                        "platform"   => "VO",
                        "state"      => TRUE,
                        "detail"     => "",
                        "input"      => $info["vo"]["serviceCode"],
                        "output"     => $estado,
                        "ws"         => "api/element/",
                        "result"     => [
                            "estado" => $estado,
                            "estado_desc" => "OK"
                        ]
                    ];
                    $return = $this->_util->setResponse(
                        $vo,
                        $data,
                        $objSql
                    );
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "Alta Servicio VO:OK",
                        "MEDIADOR"
                    );
                } else {
                    $estado = "NO SE PUDO CREAR EL SERVICIO";
                    // informacion para construir log de respuesta y error
                    $data = [
                        "messageLog"    => "ERROR: NO SE PUDO CREAR SERVICIO",
                        "type"          => "SERVICIO",
                        "platform"      => "VO",
                        "state"         => FALSE,
                        "detail"        => "",
                        "input"         => $info["vo"]["serviceCode"],
                        "output"        => $estado,
                        "ws"            => "api/service",
                        "result"        => [
                            "estado" => $estado,
                            "estado_desc" => "NOOK"
                        ]
                    ];
                    $return = $this->_util->setResponse($vo, $data, $objSql);
                    // Almacena Log de seguimiento
                    $this->_util->setLog(
                        $this->_config->urlLog,
                        "API VO Servicio:ERROR",
                        "MEDIADOR",
                        $data
                    );
                }
            } else {
                $estado = "NO SE ENCONTRO UN CLIENTE VALIDO";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "ERROR: NO SE ENCONTRO UN CLIENTE",
                    "type"          => "CLIENTE",
                    "platform"      => "VO",
                    "state"         => FALSE,
                    "detail"        => "",
                    "input"         => $info["vo"]["rut"],
                    "output"        => $estado,
                    "ws"            => "api/client/{rut}",
                    "result"        => [
                        "estado" => $estado,
                        "estado_desc" => "NOOK"
                    ]
                ];
                $return = $this->_util->setResponse($vo, $data, $objSql);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "API VO Clientes:ERROR",
                    "MEDIADOR",
                    $data
                );
            }
        } else {
            $estado = "PLANES ENVIADOS NO EXISTEN EN SISTEMA";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO SE ENCONTRO UN PLAN",
                "type"          => "PLAN",
                "platform"      => "VO",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => implode("_", $info["vo"]["planes"]),
                "output"        => $estado,
                "ws"            => "api/plan/{code}",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($vo, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "API VO Planes:ERROR",
                "MEDIADOR",
                $data
            );
        }
        return $return;
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
            case "PARAMETROS":
                // Genera estructura de retorno de error
                $str = "SE ENCONTRARON DATOS VACIOS";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = $detalle;
                break;
            case "DATA_TV":
                // Genera estructura de retorno de error
                $str = "INSTANCIA DEBE SER DE TIPO TV";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = "";
                break;
            case "DATA_PLANES":
                // Genera estructura de retorno de error
                $str = "NO ESTAN CORRECTOS LOS PLANES";
                $error["status"] = FALSE;
                $error["error"]["faultCode"]    = "DATA";
                $error["error"]["faultActor"]   = "DATOS NO VALIDOS";
                $error["error"]["faultString"]  = $str;
                $error["error"]["faultDetail"]  = "";
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
     * _validaConexionDb
     *
     * Esta función realiza la conexion a la base de datos disponible para el
     * Webservice, en caso que no se logre conexion no se continuara con nada
     * del proceso.
     *
     * @return bol
     */
    private function _validaConexionDb()
    {
        $return = TRUE;
        $db = new Db();
        $result =$db->connectionMediador();
        if($result === FALSE){
            $this->_return = $db->getError();
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Mediador:ERROR",
                "DB",
                $this->_return["default"]["error"]
            );
            $return = FALSE;
        } else {
            // Si la conexion se pudo establecer se asigna la instancia a la
            // clase Ws,para tener acceso a datos desde la clase
            $this->_dbMediador = $db->getConnection();
            $this->_sqlMediador = new Sql($this->_dbMediador);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Mediador:OK",
                "DB"
            );
            $return = TRUE;
        }
        return $return;
    }

    /**
     * _validaConexionDb
     *
     * Esta función realiza la conexion a la base de datos disponible para el
     * Webservice, en caso que no se logre conexion no se continuara con nada
     * del proceso.
     *
     * @return bol
     */
    private function _validaConexionDbMinerva()
    {
        $return = TRUE;
        $db = new Db();
        $result = $db->connectionMinerva();
        if ($result === FALSE) {
            $this->_return = $db->getError();
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Minerva:ERROR",
                "DB",
                $this->_return["default"]["error"]
            );
            $return = FALSE;
        } else {
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Conexion Minerva:OK",
                "DB"
            );
            // Si la conexion se pudo establecer se asigna la instancia a la
            // clase Ws,para tener acceso a datos desde la clase
            $this->_dbMinerva = $db->getConnection();
            $this->_sqlMinerva = new Sql($this->_dbMinerva);
            $return = TRUE;
        }
        return $return;
    }

    /**
     * _validaParametros
     *
     * Esta función realiza la validacion de los atributos de entrada, en caso
     * que al menos uno este vacio, la funcion retornara un TRUE, que indica
     * que encontro un error, por lo tanto no continuara el proceso
     *
     * @return bol
     */
    private function _validaParametros()
    {
        $retorno = TRUE;
        $detalle = [];
        foreach (get_object_vars($this) as $key => $value) {
            $campo = substr($key, 1);
            if (array_key_exists($campo, $this->_info)) {
                if (empty($value)) {
                    $detalle[] = "El argumento [". $campo."] es vacio";
                    $retorno = FALSE;
                }
            }
        }
        // Si al menos un parametro viene vacio se retorna un error
        if ($retorno === FALSE) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: LOS PARAMETROS NO SON CORRECTOS",
                "type"          => "PARAMETROS",
                "detail"        => $detalle,
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de parametros:ERROR",
                "PARAMETROS",
                $data
            );
        }else{
            // Se valida que la instancia sea para Television
            $retorno = $this->_validaInstancia();
            // Si no genero error, pasa a la siguiente validacion
            if ($retorno == TRUE) {
                // Se valida que los planes esten correctos
                $retorno = $this->_validaPlanes();
                // Si no genero error, pasa a la siguiente validacion
                if ($retorno == TRUE) {
                    // Se valida que el tipo de servicio esten correcto
                    $retorno = $this->_validaTipoServicio();
                    // Si no genero error, pasa a la siguiente validacion
                    if ($retorno == TRUE) {
                        // Se valida que los servicios esten correctos
                        $retorno = $this->_validaServicios();
                        // Si no genero error, pasa a la siguiente validacion
                        if ($retorno == TRUE) {
                            // Se valida direccion que este correcta
                            $retorno = $this->_validaDireccion();
                            // Si no genero error, pasa a la siguiente validacion
                            if ($retorno == TRUE) {
                                // Se valida ciudad que este correcta
                                $retorno = $this->_validaCiudad();
                                // Si no genero error, pasa a la siguiente validacion
                                if ($retorno == TRUE) {
                                    // Se valida la region que este correcta
                                    $retorno = $this->_validaRegion();
                                }
                            }
                        }
                    }
                }
            }
        }
        if($retorno === TRUE){
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de parametros:OK",
                "PARAMETROS"
            );
        }
        return $retorno;
    }

    /**
     * _validaCiudad
     *
     * @return void
     */
    private function _validaCiudad()
    {
        $retorno = TRUE;
        // Se valida que la instancia sea para Television
        $ciudad = $this->_ciudad;
        if (strlen($ciudad) > 40) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: CIUDAD NO VALIDA",
                "type"          => "DATA_TV"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de ciudad:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }

    /**
     * _validaDireccion
     *
     * @return void
     */
    private function _validaDireccion()
    {
        $retorno = TRUE;
        // Se valida que la instancia sea para Television
        $direccion = $this->_direccion;
        if (strlen($direccion) > 80) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: DIRECCION NO VALIDA",
                "type"          => "DATA_TV"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de direccion:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }

    /**
     * _validaInstancia
     *
     * @return void
     */
    private function _validaInstancia()
    {
        $retorno = TRUE;
        // Se valida que la instancia sea para Television
        $instancia = $this->_instancia;
        if (substr($instancia, 0, 3) != "GTD"
         && substr($instancia, 0, 2) != "TV") {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO ES INSTANCIA DE TV",
                "type"          => "DATA_TV"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de instancia:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }

    /**
     * _validaPlanes
     *
     * @return void
     */
    private function _validaPlanes()
    {
        $retorno    = TRUE;
        $error      = FALSE;
        $msj        = "";
        // Se valida que los planes vengan correctamente
         // Prepara planes
        $planes = $this->_util->parseData($this->_planes, ",");
        $fechas = array();
        // Se valida que la estructura de los planes venga bien armada
        if (!preg_match("/^'([0-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/(\d{4})','(([0-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/(\d{4}))*',(\d{1,4})(\|\d{1,4})*$/i", $this->_planes)
            && !strstr($this->_planes, "IPTV Santiago")) {
            $error = TRUE;
            $msj   = "PLANES NO VALIDOS";
        }
        // Re recorren los planes para verificar si
        // hay algun problema con las fechas
        foreach ($planes as $key => $value) {
            if (strstr($value, "/")) // si no esta vacia la fecha de fin
            {
                $fecha = str_replace("'", "", $value);
                //devuelve diferencia en dias entre 2 fechas
                $diff = $this->_util->compareDate(date("d/m/Y"), $fecha);
                //fecha, fin o inicio, es menor a la fecha actual
                if ($diff > 0) {
                    $error = TRUE;
                    $msj   = "FECHAS EN PLANES NO VALIDAS";
                }
                $fechas[] = $fecha;
            } elseif (strstr($value, "|")
                  || (strlen($value) <= 4 && $value != "''")
                  || $value == "IPTV Santiago") {
                if (count($fechas) == 2) {
                    $fecha_ini = $fechas[0];
                    $fecha_fin = $fechas[1];
                    //devuelve diferencia en dias entre 2 fechas
                    $diff = $this->_util->compareDate($fecha_ini, $fecha_fin);
                    //fecha fin menor a fecha inicio
                    if ($diff > 0) {
                        $error = TRUE;
                        $msj   = "FECHAS INICIO DE PLANES NO VALIDOS";
                    }
                }
            }
        }
        // Si se presento algun error en cualquier parte de la validación se
        // informa el error
        if($error === TRUE){
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: " . $msj,
                "type"          => "DATA_PLANES"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de planes:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }
        return $retorno;
    }

    /**
     * _validaServicios
     *
     * @return void
     */
    private function _validaServicios()
    {
        $retorno = TRUE;
        $error      = FALSE;
        $msj        = "";
        // Se valida que los servicios vengan correctamente
        // Prepara planes
        $servicios = $this->_util->parseData($this->_servicios, ",");
        $fechas = array();
        // Se valida que los servicios tengan el formato correcto
        if (!preg_match("/^'([0-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/(\d{4})','(([0-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/(\d{4}))*',(\d{1,4})(\|\d{1,4})*$/i", $this->_servicios)) {
                $error = TRUE;
                $msj   = "PLANES NO VALIDOS";
        }
        // Se recorren los servicios para verificar si hay
        // algun problema con las fechas
        foreach ($servicios as $key => $value) {
            // si no esta vacia la fecha de fin
            if (strstr($value, "/")) {
                $fecha = str_replace("'", "", $value);
                //devuelve diferencia en dias entre 2 fechas
                $diff = $this->_util->compareDate(date("d/m/Y"), $fecha);
                //fecha, fin o inicio, es menor a la fecha actual
                if ($diff > 0) {
                    $error = TRUE;
                    $msj   = "FECHAS EN SERVICIOS NO VALIDAS";
                }
                $fechas[] = $fecha;
            } elseif (strstr($value, "|")
            || (!strstr($value, "/")
            && $value != "''")) {
                if (count($fechas) == 2) {
                    $fecha_ini = $fechas[0];
                    $fecha_fin = $fechas[1];
                    //devuelve diferencia en dias entre 2 fechas
                    $diff = $this->_util->compareDate($fecha_ini, $fecha_fin);
                    //fecha fin menor a fecha inicio
                    if ($diff > 0) {
                        $error = TRUE;
                        $msj   = "FECHAS INICIO DE PLANES NO VALIDOS";
                    }
                }
            }
        }
        // Si se presento algun error en cualquier parte de la validación se
        // informa el error
        if ($error === TRUE) {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: " . $msj,
                "type"          => "DATA_PLANES"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de servicios:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }
        return $retorno;
    }

    /**
     * _validaTipoServicio
     *
     * @return void
     */
    private function _validaTipoServicio()
    {
        $retorno = TRUE;
        // Se valida que el tipo de servicio sea el correcto
        $tipo_servicio = $this->_tipoServicio;
        if ($tipo_servicio != "IPTV"
        && !strstr($tipo_servicio, "FTTH")
        && $tipo_servicio != "IPTV_EMPRESAS") {
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: TIPO SERVICIO NO VALIDO",
                "type"          => "DATA_TV"
            ];
            $this->_return = $this->_util->setResponse($this, $data);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "Validacion de TIPO SERVICIO:ERROR",
                "PARAMETROS",
                $data
            );
            $retorno = FALSE;
        }

        return $retorno;
    }

    /**
     * _validaRegion
     *
     * @return bol
     */
    private function _validaRegion()
    {
        $retorno = TRUE;
        //cliente residencial
        if (!strstr($this->_tipoServicio, "EMPRESA")) {
            //por defecto region RF (993)
            $region = "RM";
            if (strstr($this->_tipoServicio, "IPTV")) {
                //region unica para IPTV (853)
                $region = "IPTV_RESIDENCIAL_STGO";
            }
            $codigoRegion = $this->_sqlMediador->getRegion($region);
        } else {
            //corporativo
            $resultPlanes = $this->_preparaPlanes($this->_planes);
            $pM = $resultPlanes["numeroPlanes"];
            $iptv = $this->_sqlMediador->getRegByPlan(trim($pM, "|"));
            $codigoRegion = $iptv['REGION'];
        }
        if ($codigoRegion == NULL) {
            $retorno = FALSE;
        }
        return $retorno;
    }

    /**
     * _validaWidgets
     * @param  mixed $ws
     *
     * @return bol
     */
    private function _validaWidgets($ws)
    {
        $retorno = TRUE;
        $servicios = $this->_util->parseData($this->_servicios, ",");
        // Se cargan solo los servicios para evaluar los widgets
        $vecServicios = $this->_util->parseData($servicios[2], "|");
        //valida que al menos uno de los servicios sea de Widget
        $validaWidgets = $ws->validarWidget($vecServicios);
        if ($validaWidgets === FALSE) {
            $retorno = FALSE;
        }
        return $retorno;
    }



}
