<?php

/**
 * Class TvRefrescaServicio para actualización de planes Servicio de Televisión
 *
 * Esta clase permite realizar el proceso de actualizacion de planes de Servicio
 * para Minerva y VO
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
 * TvRefrescaServicio
 */
class TvRefrescaServicio
{
    private $_instanciaSubCuenta;
    private $_planes;
    private $_macs;
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
        $this->_instanciaSubCuenta  = $info["instanciaSubCuenta"];
        $this->_planes              = $info["planes"];
        $this->_macs                = $info["macs"];
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
     * refrescaServicio
     *
     * Esta función genera la actualización de planes de servicio para las
     * plataformas definidas
     *
     * @return struct
     */
    public function refrescaServicio()
    {
        $return = [];
        // Almacena Log de seguimiento
        $this->_util->setLog(
            $this->_config->urlLog,
            "A C T U A L I Z A    P L A N E S    D E L    S E R V I C I O",
            "MEDIADOR",
            "Inicia proceso actualización de planes de servicio en Minerva y Vo",
            "INICIO",
        );
        // Se valida si existe conexion con base de datos
        $validaDb        = $this->_validaConexionDb();
        $validaDbMinerva = $this->_validaConexionDbMinerva();
        if ($validaDb && $validaDbMinerva) {
            // Se registra transaccion al iniciar proceso
            $this->_idConsulta = $this->_util->startTransaction(
                "actualiza_planes",
                $this->_sqlMediador,
                $this->_info,
            );
            // Se valida si los parametros traen la información correcta
            $validaParametros = $this->_validaParametros();
            if ($validaParametros === TRUE) {
                // Se invoca el proceso que actualiza los planes en Minerva
                $return = $this->_refrescaServicioMinerva();
                $this->_return["default"] = $return["default"];
                $this->_return["minerva"] = $return["minerva"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }

                // Se invoca el proceso que actualiza los planes en VO
                $return = $this->_refrescaServicioVO();
                $this->_return["default"] = $return["default"];
                $this->_return["vo"]      = $return["vo"];
                if ($this->_return["default"]["status"] === FALSE) {
                    return $this->_return;
                }

                // Se evalua si ocurrio alguna excepcion
                if ($this->_return["default"]["status"] !== FALSE) {
                    // Se registra transaccion al finalizar proceso
                    $this->_idConsulta = $this->_util->endTransaction(
                        "respuesta_actualiza_planes",
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
                        "A C T U A L I Z A    P L A N E S    D E L    S E R V I C I O",
                        "MEDIADOR",
                        "Finaliza proceso de actualización de planes en Minerva y Vo",
                        "FIN",
                    );
                }
            }
        }
        return $this->_return;
    }

    /**
     * _refrescaServicioMinerva
     *
     * Esta función genera la actualización de planes en la plataforma Minerma
     *
     * @return void
     */
    private function _refrescaServicioMinerva()
    {
        $return = [];
        $ws = new Ws();
        // Se envia la instancia de la base de datos del mediador a Minerva
        $ws->setInstanceDb($this->_dbMediador);
        // Se envia la instancia de la base de datos de minerva a Minerva
        $ws->setInstanceDbMinerva($this->_dbMinerva);
        // Verifica que la plataforma Minerva este activa para Actualización
        $rsPlataforma = $this->_sqlMediador->getPlatform("Minerva");
        // Verifica que la plataforma este activa para Actualización
        if ($rsPlataforma === TRUE) {
            // Obtiene la Url del Ws a ser invocado
            $urlWs =  $this->_sqlMediador->getUrlWs("AGREGA_PLAN");
            $rsVAlidaUrl = $this->_util->validateUrl($urlWs);
            // Valida si la URL del WS de Minerva esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Prepara los datos para Actualizacion de planes de Minerva
                $validaPrepara = $this->_prepararDatosMinerva($ws);
                if ($validaPrepara) {
                    // Procesa Actualización de planes de servicio Minerva
                    // La respuesta del proceso  es almacena en
                    // $this->_return tanto la respuesta del proceso como la
                    // respuesta del error, en caso de existir
                    $return = $this->setRefrescaServicioMinerva($ws, $this->_aux);
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
     * _refrescaServicioVo
     *
     * Esta función genera la actualización de planes en la plataforma VO
     *
     * @return strunct
     */
    private function _refrescaServicioVo()
    {
        $return = [];
        $vo = new Vo();
        // Se envia la instancia de la base de datos del mediador a VO
        $vo->setInstanceDb($this->_dbMediador);
        $rsPlataforma = $this->_sqlMediador->getPlatform("Vo");
        // Verifica que la plataforma Vo este activa para Actualizacion
        if ($rsPlataforma === TRUE) {
            $rsVAlidaUrl = $this->_util->validateUrl($this->_config->urlVo);
            // Valida si la URL del WS de VO esta respondiendo
            if ($rsVAlidaUrl === TRUE) {
                // Obtiene el token para conexxion con el WS
                $rsToken = $vo->setToken();
                // Valida que el token se haya generado
                if($rsToken === TRUE) {
                    // Prepara los datos para actualización de planes de VO
                    $validaPrepara = $this->_prepararDatosVo();
                    if ($validaPrepara) {
                        // Procesa actualización de planes de servicio VO
                        $return = $this->_setRefrescaServicioVo(
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
     * _prepararDatosMinerva
     *
     * Esta función prepara los datos previos al consumo del WS de la
     * la plataforma Minerva
     *
     * @return struct
     */
    private function _prepararDatosMinerva($ws)
    {
        $retorno = FALSE;

        // Fecha hoy
        $dateToday = date("d/m/Y");
        $prefijo        = $this->_config->prefix;

        // Prepara Instancia, Rut y Subcuentas
        $resultado  = $this->_util->splitAccount($this->_instanciaSubCuenta);
        $instancia  = $resultado["instancia"];
        $subcuentas = $resultado["subcuentas"];
        $rut = $resultado["rut"];
        $this->_aux["minerva"]["instancia"]     = $instancia;
        $this->_aux["minerva"]["rut"]           = $rut;
        $this->_aux["minerva"]["subcuentas"]    = $subcuentas;

        // Prepara macs
        $macs = $this->_util->parseData($this->_macs);
        $this->_aux["minerva"]["macs"] = $macs;

        // Prepara Planes existentes
        $rsPlanes = $ws->getPlanesMinervaByCustomerId($prefijo . $instancia);
        $this->_aux["minerva"]["planesActuales"] = $rsPlanes;

        // Prepara planes
        $planes = $this->_util->parseData($this->_planes, ",");
        // Se carga la posicion 2, la cual es la que trae los planes
        $strPlanes = $planes[2];
        $vecPlanes = $this->_util->parseData($strPlanes, "|");
        $planesSolicitud = [];
        $pM = "";
        if (strstr($strPlanes, "|") || (strlen($strPlanes) <= 4
         && !empty($strPlanes))) {
            foreach ($vecPlanes as $key => $value) {
                $codPlan = $this->_sqlMediador->getCodeMinerva($value);
                $planesSolicitud[] = $codPlan;
                // Valida si el plan se debe incluir o no en el proceso
                $validaPlan=$this->_setPlanesEliminar(
                    $value,
                    $codPlan,
                    $rsPlanes,
                    $planes);
                if($validaPlan === TRUE){
                    // Solo entra aqui si ha pasado por todas las validaciones
                    // previas de planes existentes, y planes actuales
                    $pM .= $codPlan . "|";
                }
            }
            if(!empty($pM)){
                $planes[2] = trim($pM, "|");
                $planes = implode(",", $planes);
            } else {
                 $planes = "";
            }
        }

        // Se prepara planes a eliminar
        $planesEliminar = "";
        foreach ($rsPlanes as $value) {
            // Se buscan cuales son los planes que se deben eliminar
            if (!in_array($value["codigo"], $planesSolicitud)) {
                $planesEliminar .= $value["codigo"] . "|";
            }
        }
        // Si se encuentran planes a eliminar se prepara la estructura
        // de eliminación
        if (!$planesEliminar == "") {
            // Fecha Actual
            $fc = date("d/m/Y");
            $planesEliminar = "'" . $fc . "','" . $fc . "'," . trim($planesEliminar, "|");
        }

        $this->_aux["minerva"]["planesSolicitud"]    = $planesSolicitud;
        $this->_aux["minerva"]["planesEliminar"]     = $planesEliminar;
        $this->_aux["minerva"]["planesAdicionar"]    = $planes;

        // Prepara servicios
        $servicios  = "";
        $servicios  = "'" . $dateToday . "','',";
        $servicios .= $this->_sqlMediador->getServiceByName("INTERNET") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("HD") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("WIDGETS") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("CALLERID") . "|";
        $servicios .= $this->_sqlMediador->getServiceByName("Remote Scheduling");
        $this->_aux["minerva"]["servicios"] = $servicios;

        // Prepara estructura de consumo de WS para aprovisionamiento
        // del servicio de refresco  en Minerva
        $parametros = array("cCustomerId" => $prefijo . $instancia);
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
        $prefijo = $this->_config->prefix;

        $resultado  = $this->_util->splitAccount($this->_instanciaSubCuenta);

        // Prepara rut
        $rut = $resultado["rut"];
        $this->_aux["vo"]["rut"]     = $rut;

        // Prepara instancia
        $instancia  = $resultado["instancia"];
        $this->_aux["vo"]["serviceCode"] = $prefijo . $instancia;

        // Prepara macs
        $macs = $this->_util->parseData($this->_macs);
        $this->_aux["vo"]["macs"] = $macs;

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

    /**
     * setRefrescaServicioMinerva
     *
     * @param  mixed $ws
     * @param  mixed $info
     * @return void
     */
    public function setRefrescaServicioMinerva($ws, $info)
    {
        $return             = [];
        $objSql             = $this->_sqlMediador;
        $parametros         = $info["minerva"]["parametros"];
        $prefijo            = $this->_config->prefix;
        $instancia          = $info["minerva"]["instancia"];
        $servicios          = $info["minerva"]["servicios"];
        $planesSolicitud    = $info["minerva"]["planesSolicitud"];
        $planesAdicionar    = $info["minerva"]["planesAdicionar"];
        $planesActuales     = $info["minerva"]["planesActuales"];
        $planesEliminar     = $info["minerva"]["planesEliminar"];
        $rsAlta = TRUE;
        $rsBaja = TRUE;
        $rsOther = TRUE;

        // Valida que el servicio tenga al menos un plan cargado actual, de
        // lo contrario no se procede ya que no es habitual tener un servicio
        // sin planes.
        if($planesActuales !== -1) {
            // Adiciona los planes solicitados
            if ($planesAdicionar != ""){
                $rsAlta=$ws->setAlta($instancia, $planesAdicionar);
                if($rsAlta === FALSE) {
                    $estado = "RESPUESTA DE WS AGREGA_PLAN NO ES CORRECTA";
                    // informacion para construir log de respuesta y error
                    $input = implode("_", $parametros) . "_" . $planesAdicionar;
                    $data = [
                        "messageLog"    => "ERROR: WS ",
                        "type"          => "RESPUESTA",
                        "platform"      => "MINERVA",
                        "state"         => FALSE,
                        "detail"        => "",
                        "input"         => $input,
                        "output"        => $estado,
                        "ws"            => "AGREGA_PLAN",
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
            }
            // Elimina los planes que no se deben tener
            if ($planesEliminar != "") {
                $rsBaja = $ws->setBaja($instancia, $planesEliminar);
                if ($rsBaja === FALSE) {
                    $estado = "RESPUESTA DE WS ELIMINA_PLAN NO ES CORRECTA";
                    // informacion para construir log de respuesta y error
                    $input = implode("_", $parametros) . "_" . $planesEliminar;
                    $data = [
                        "messageLog"    => "ERROR: WS ",
                        "type"          => "RESPUESTA",
                        "platform"      => "MINERVA",
                        "state"         => FALSE,
                        "detail"        => "",
                        "input"         => $input,
                        "output"        => $estado,
                        "ws"            => "ELIMINA_PLAN",
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
            }
            // Adiciona los planes de otros servicios
            if (count($ws->otherServices) < 5) {
                $rsOther= $ws->setOtherServices($instancia, $servicios);
                if ($rsOther === FALSE) {
                    $estado = "RESPUESTA DE WS AGREGA_PLAN OTROS SERVICIOS NO ES CORRECTA";
                    // informacion para construir log de respuesta y error
                    $input = implode("_", $parametros) . "_" . $servicios;
                    $data = [
                        "messageLog"    => "ERROR: WS ",
                        "type"          => "RESPUESTA",
                        "platform"      => "MINERVA",
                        "state"         => FALSE,
                        "detail"        => "",
                        "input"         => $input,
                        "output"        => $estado,
                        "ws"            => "AGREGA_PLAN",
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
            }
            // Valida que todos los procesos hayan sido exitosos
            if($rsAlta === TRUE && $rsBaja === TRUE && $rsOther=== TRUE) {
                $estado = "ACTUALIZACION DE PLANES CORRECTO";
                // informacion para  log de respuesta y error
                $input = implode("_", $parametros) . "_" . implode("_", $planesSolicitud);
                $data = [
                    "messageLog"    => "OK:PROCESO CORRECTO",
                    "type"          => "OK",
                    "platform"      => "MINERVA",
                    "state"         => TRUE,
                    "detail"        => "",
                    "input"         => $input,
                    "output"        => $estado,
                    "ws"            => "AGREGA_PLAN",
                    "result"        => [
                        "estado" => $estado,
                        "estado_desc" => "OK"
                    ]
                ];
                $return = $this->_util->setResponse(
                    $ws,
                    $data,
                    $objSql
                );
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "Asignación de planes Minerva:OK",
                    "MEDIADOR"
                );
                // Se obtienen los planes definitivos
                $rsPlanesActual = $ws->getPlanesMinervaByCustomerId(
                    $prefijo . $instancia
                );
                // Se convieten los planes en String para enviar a
                // setChannelsPortal
                $planesMinerva = "";
                foreach ($rsPlanesActual as $value) {
                    $planesMinerva .= $value["codigo"] . "|";
                }
                // Se verifica los canales contratados
                $ws->setChannelsPortal(
                    $prefijo . $instancia,
                    $planesMinerva
                );
            }
        } else {
            $estado = "NO EXISTE INSTANCIA EN MINERVA";
            // informacion para construir log de respuesta y error
            $input = $input = implode("_", $parametros) . "_" . implode("_", $planesSolicitud);
            $data = [
                "messageLog"    => "ERROR: NO EXISTE INSTANCIA EN MINERVA",
                "type"          => "DATA",
                "platform"      => "MINERVA",
                "state"         => FALSE,
                "detail"        => "",
                "input"         => $input,
                "output"        => $estado,
                "ws"            => "INFO_N_SERVICIO_V2",
                "result"        => [
                    "estado" => $estado,
                    "estado_desc" => "NOOK"
                ]
            ];
            $return = $this->_util->setResponse($ws, $data, $objSql);
            // Almacena Log de seguimiento
            $this->_util->setLog(
                $this->_config->urlLog,
                "WS Minerva Instancia:ERROR",
                "MEDIADOR",
                $data
            );
        }
        return $return;
    }

    /**
     * _setRefrescaServicioVo
     *
     * @param  mixed $info
     * @param  mixed $info
     * @return void
     */
    private function _setRefrescaServicioVo($vo, $info)
    {
        $return   = [];
        $objSql = $this->_sqlMediador;

        // Se valida que los planes que se enviaron esten activos y disponibles
        // todos los planes deben validarse como TRUE, si al menos uno de los
        // planes enviados no esta bien, el proceso se desecha
        $rsPlan = $vo->validatePlanes($info);
        if ($rsPlan === TRUE) {
            // Se verifica que el servicio exista, en caso que no exista se
            // retorna error, porque sin servicio no se puede actualizar
            $rsServicio = $vo->setServicioCambioPlan($info);
            if ($rsServicio === TRUE) {
                //Respuesta OK
                $estado = "ACTUALIZACION DE PLANES CORRECTO";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog" => "OK: PROCESO CORRECTO",
                    "type"       => "OK",
                    "platform"   => "VO",
                    "state"      => TRUE,
                    "detail"     => "",
                    "input"      => $info["vo"]["serviceCode"],
                    "output"     => $estado,
                    "ws"         => "api/service/{servicecode}",
                    "result"     => [
                        "estado" => $estado,
                        "estado_desc" => "OK"
                    ]
                ];
                $return = $this->_util->setResponse($vo, $data, $objSql);
                // Almacena Log de seguimiento
                $this->_util->setLog(
                    $this->_config->urlLog,
                    "Actualiza Planes Servicio VO:OK",
                    "MEDIADOR",
                    $data
                );
            } else {
                // Se obtiene la respuesta de la causa del fallo en el WS
                $responseApi = $vo->getResponseApi();
                $estado = "NO SE PUDO ACTUALIZAR LOS PLANES DEL SERVICIO [ " . $responseApi["message"] . " ]";
                // informacion para construir log de respuesta y error
                $data = [
                    "messageLog"    => "ERROR: NO SE PUDO ACTUALIZAR PLANES [ " . $responseApi["message"] . " ]",
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
            // Se obtiene la respuesta de la causa del fallo en el WS
            $responseApi = $vo->getResponseApi();
            $estado = "PLANES ENVIADOS NO EXISTEN EN SISTEMA [ " . $responseApi["message"] . " ]";
            // informacion para construir log de respuesta y error
            $data = [
                "messageLog"    => "ERROR: NO SE ENCONTRO UN PLAN [ " . $responseApi["message"] . " ]",
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

    private function _setPlanesEliminar($plan, $codPlan, $rsPlanes, $planes)
    {
        $retorno = FALSE;
        $this->_aux["minerva"]["planesEliminar"] = [];
        //encontro codigo en minerva para el plan de gb
        if ($codPlan != "") {
            // Revisa planes actuales
            if (!empty($rsPlanes)){
                // Se busca el codigo del plan en los planes ya cargados
                $key = $this->_util->searchByCode($codPlan, $rsPlanes);
                // Si ell plan no esta cargado, se debe retornar TRUE para
                // ser agregado.
                if (!$key && !is_int($key)) {
                    $retorno = TRUE;
                } else {
                    // Se deja en vacio la fecha fin, en caso que no se haya
                    // asignado
                    if($planes[1]=="''"){
                        $planes[1]="";
                    }
                    // Si el plan se encontro se debe validar las fechas de fin
                    // para ver si se debe eliminar el anterior  crear el actual
                    $ff = str_replace("/", "-", $rsPlanes[$key]['fecha_fin']);
                    error_log("plan " . $codPlan . " encontrado  con fecha fin " . $rsPlanes[$key]['fecha_fin'] . ".");
                    // Si trae una fecha fin la comparo con la que tiene minerva
                    $ffAct = (isset($planes[1]) ? $planes[1] : "");
                    error_log("fecha Minerva=>" . $ff . "fecha plan actual => " . $ffAct);
                    // Si es diferente la fecha lo agendo para agregar
                    // y eliminar el antiguo.
                    if ($ff != $ffAct) {
                        $retorno = TRUE;
                        $this->_aux["minerva"]["planesEliminar"][] = $codPlan;
                    } else {
                        error_log("fechas son iguales , no se hace nada, se deja el plan tal cual");
                        $retorno = FALSE;
                    }
                }

            }else{
                error_log("No habia nada cargado en Minerva, agrego el plan " . $codPlan . " equivalente a " . $plan . " en Gb");
                $retorno = TRUE;
            }
        }else{
            error_log(" Plan GB " . $plan . " No tiene equivalente en Minerva");
            $retorno = FALSE;
        }

        return $retorno;
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
            // S i la conexion se pudo establecer se asigna la instancia a la
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
            // Si la conexion se pudo establecer se asigna la instancia a la
            // clase Ws,para tener acceso a datos desde la clase
            $this->_dbMinerva = $db->getConnection();
            $this->_sqlMinerva = new Sql($this->_dbMinerva);
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
        }
        if ($retorno === TRUE) {
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
     * _validaInstancia
     *
     * @return void
     */
    private function _validaInstancia()
    {
        $retorno = TRUE;
        // Se valida que la instancia sea para Television
        $resultado = $this->_util->splitAccount($this->_instanciaSubCuenta);
        if (!strstr($resultado["instancia"], "TV")) {
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
}
