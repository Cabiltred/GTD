<?php

/**
 * Class Sql
 *
 * Esta clase contiene funciones que realizan operaciones DML en la
 * base de datos como SELECT, UPDATE, INSERT, DELETE
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

/**
 * Sql
 */
class Sql
{
    private $_conexion;
    /**
     * __construct
     *
     * @return void
     */
    public function __construct($conexion)
    {
        $this->_conexion = $conexion;
    }

    /**
     * getCodeMinerva
     *
     * Funcion que obtiene el codigo de un plan Minerva(Service_menu_id)
     * comparando con el plan id de goback que vendrá en el método
     * de alta_servicio
     *
     * @param  mixed $codigo_goback
     * @return void
     */
    public function getCodeMinerva($code)
    {
        $codigo = FALSE;
        //error_log("Codigo goback " . $code);
        $sql = "SELECT codigo_minerva
                FROM plan_telsur_gb
                WHERE codigo_goback=" . $code;
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $codigo = $row->codigo_minerva;
            }
        }

        return $codigo;
    }

    /**
     * getCodMinervaByNameMinerva
     *
     * @param  mixed $nombre_minerva
     * @return void
     */
    public function getCodMinervaByNameMinerva($nombre_minerva)
    {
        $codigo = FALSE;
        $sql = "SELECT codigo_minerva
                FROM plan_telsur_gb
                WHERE nombre_minerva='" . $nombre_minerva . "'";
        // Execute our query
        if ($this->_conexion->Query($sql)) {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();

                $codigo = $row->codigo_minerva;
            }
        }

        return $codigo;
    }

    /**
     * getPlataforma
     *
     * @param  mixed $plataforma
     * @return void
     */
    public function getPlatform($plataforma)
    {
        $retorno = FALSE;
        $sql = "SELECT activa
                FROM plataforma
                WHERE LOWER(nombre)='" . strtolower($plataforma) . "'";
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                if($row->activa === "1"){
                    $retorno = TRUE;
                }else{
                    $retorno = FALSE;
                }
            }
        }
        return $retorno;
    }

    /**
     * getRegion
     *
     * Funcion que devuelve la region activa para clientes residenciales(Gtd)
     *
     * @param  mixed $region
     * @return void
     */
    public function getRegion($region)
    {
        $sql = "SELECT cod_region
                FROM region
                WHERE nombre='" . $region . "'";
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $region = $row->cod_region;
            }
        }
        return $region;
    }

    /**
     * getServiceByName
     *
     * @param  mixed $name
     * @return void
     */
    public function getServiceByName($name)
    {
        $sql = "SELECT service_menu_id
                FROM service
                WHERE name='" . $name . "'";

        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $service_menu_id = $row->service_menu_id;
            }
        }
        return $service_menu_id;
    }

    /**
     * getServiceWidget
     *
     * @param  mixed $code
     * @return void
     */
    public function getServiceWidget($code)
    {
        $retorno = FALSE;
        $sql = "SELECT name
                FROM service
                WHERE netcracker_id = '" . trim($code) . "'";

        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $name = $row->name;
                if (strstr($name, "Widget")) {
                    $retorno = TRUE;
                }
            }
        }
        return $retorno;
    }

    /**
     * getRegByPlan
     *
     * @param  mixed $planes
     * @return void
     */
    public function getRegByPlan($planes)
    {
        $response = array();
        $reg = "";
        $widget = "";
        if (is_array($planes)) {
            foreach ($planes as $a => $p) {
                $sql = "SELECT *
                        FROM iptv_empresas
                        WHERE codigo_minerva = '" . $p['codigo'] . "'";
                if (!$this->_conexion->Query($sql)) {
                    $this->_conexion->Kill();
                } else {
                    $this->_conexion->MoveFirst();
                    while (!$this->_conexion->EndOfSeek()) {
                        $row = $this->_conexion->Row();
                        $reg = $row->cod_region;
                        $widget = $row->widget;
                        break;
                    }
                }
            }
        } else {
            if (strstr($planes, ";")) {
                $planes = explode(";", $planes);
            } else {
                $planes = explode("|", $planes);
            }

            foreach ($planes as $p) {
                $sql = "SELECT *
                        FROM iptv_empresas
                        WHERE codigo_minerva = '" . $p . "'";
                if (!$this->_conexion->Query($sql)) {
                    $this->_conexion->Kill();
                } else {
                    $this->_conexion->MoveFirst();
                    while (!$this->_conexion->EndOfSeek()) {
                        $row = $this->_conexion->Row();
                        $reg = $row->cod_region;
                        $widget = $row->widget;
                        break;
                    }
                }
            }
        }
        $response = array("REGION" => $reg, "WIDGET" => $widget);
        return $response;
    }

    /**
     * getServiceByNcId
     *
     * @param  mixed $idservicio
     * @return void
     */
    public function getServiceByNcId($idservicio)
    {
        $sql = "SELECT service_menu_id
                FROM service
                WHERE netcracker_id = '" . trim($idservicio) . "'";
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $service_menu_id = $row->service_menu_id;
            }
        }

        return $service_menu_id;
    }

    /**
     * getInfoSocket
     *
     * @return void
     */
    public function getInfoSocket()
    {
        $sql = "SELECT ip,puerto
                FROM socket
                WHERE nombre='SocketPrimario'";
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();

                $ip = $row->ip;
                $puerto = $row->puerto;
            }
        }
        $info = array("ip" => $ip, "puerto" => $puerto);
        return $info;
    }

    /**
     * getStb
     *
     * @param  mixed $serie
     * @return struct
     */
    public function getStb($serie)
    {
        $info = [];
        $sql = "SELECT serie,marca,modelo
                FROM stb
                WHERE serie=".$serie;
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $info= [
                    "serie"=>$row->serie,
                    "marca" => $row->marca,
                    "modelo" => $row->modelo,
                ];
            }
        }
        return $info;
    }

    /**
     * getStbAll
     *
     * @param  mixed $serie
     * @return struct
     */
    public function getStbAll()
    {
        $info = [];
        $sql = "SELECT serie,marca,modelo
                FROM stb";
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();
                $info[$row->serie] = [
                    "serie" => $row->serie,
                    "marca" => $row->marca,
                    "modelo" => $row->modelo,
                ];
            }
        }
        return $info;
    }

    /**
     * getUrlWs
     *
     * @param  mixed $nombre
     * @return void
     */
    public function getUrlWs($nombre)
    {
        $sql = "SELECT url
                FROM webservice
                WHERE nombre='" . $nombre . "'";
        // Execute our query
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        } else {
            $this->_conexion->MoveFirst();
            while (!$this->_conexion->EndOfSeek()) {
                $row = $this->_conexion->Row();

                $url = $row->url;
            }
        }
        return $url;
    }

    /**
     * setTransaction
     *
     * @param  mixed $tipo
     * @param  mixed $parameters
     * @return void
     */
    public function setTransaction($tipo, $parameters, $info)
    {
        $input = $parameters;
        switch ($tipo) {
            case "respuesta_alta_servicio":
            case "respuesta_alta_servicio_corp":
            case "respuesta_baja_servicio":
            case "respuesta_baja_servicio_corp":
            case "respuesta_corte_servicio":
            case "respuesta_repo_servicio":
            case "respuesta_modifica_servicio":
            case "respuesta_actualiza_planes":
            case "respuesta_actualiza_planes_corp":
            case "respuesta_del_stb_corp":
            case "respuesta_add_stb_corp":
                $datos = explode("|", $parameters);
                $sql = sprintf(
                    'INSERT INTO respuesta(output,
                                                      respuesta_minerva,
                                                      respuesta_gb,
                                                      consulta_id_consulta)
                                VALUES ("%s","%s","%s",%d)',
                    $datos[0],
                    $datos[1],
                    $datos[2],
                    $datos[3]
                );
                break;
            case "corte_servicio":
            case "repo_servicio":
                $sql = sprintf(
                    'INSERT INTO consulta_corte_repo(tipo,
                                                     instancia,
                                                     rut,
                                                     planes,
                                                     desde)
                                VALUES ("%s","%s","%s","%s","%s")',
                    $tipo,
                    $info["instancia"],
                    $info["rut"],
                    $info["planes"],
                    $_SERVER["REMOTE_ADDR"]
                );
                break;
            default:
                $sql = sprintf('INSERT INTO consulta (tipo,input)
                                VALUES ("%s","%s")', $tipo, $input);
                break;
        }

        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        }

        return $this->_conexion->GetLastInsertID();
    }

    /**
     * saveChannelsPortal
     *
     * @param  mixed $canales
     * @param  mixed $customerId
     * @return void
     */
    public function saveChannelsPortal($canales, $customerId)
    {
        $sql = sprintf(
            "INSERT INTO canal_cliente (customer_id,canales)
                        VALUES ('%s','%s')
                        ON DUPLICATE
                        KEY UPDATE canales='%s'",
            $customerId,
            $canales,
            $canales
        );
        error_log($sql);
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        }

        return $this->_conexion->GetLastInsertID();
    }

    /**
     * saveError
     *
     * @param  mixed $plataforma
     * @param  mixed $input
     * @param  mixed $output
     * @param  mixed $nws
     * @return void
     */
    public function setError($plataforma, $input, $output, $nws)
    {
        $fecha = date("Y-m-d h:i:s");
        $sql = sprintf(
            'INSERT INTO mediador_error(plataforma,
                                                   nombre_ws,
                                                   input,
                                                   output,
                                                   fecha_error,
                                                   estado)
                        VALUES ("%s","%s","%s","%s","%s",%d)',
            $plataforma,
            $nws,
            $input,
            $output,
            $fecha,
            0
        );
        if (!$this->_conexion->Query($sql)) {
            $this->_conexion->Kill();
        }

        return $this->_conexion->GetLastInsertID();
    }

    /**
     * validateWidget
     *
     * @param  mixed $codigos
     * @return void
     */
    public function validateWidget($codigos)
    {
        foreach ($codigos as $cod) {
            $sql = "SELECT name
                    FROM service
                    WHERE netcracker_id = '" . trim($cod) . "'";
            if (!$this->_conexion->Query($sql)) {
                $this->_conexion->Kill();
            } else {
                $this->_conexion->MoveFirst();
                while (!$this->_conexion->EndOfSeek()) {
                    $row = $this->_conexion->Row();
                    $name = $row->name;
                    if (strstr($name, "Widget")) {
                        return TRUE;
                    }
                }
            }
        }
        return FALSE;
    }
}
