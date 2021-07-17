<?php

/**
 * Class Util
 *
 * Esta clase contiene funciones de proposito general que pueden ser usadas
 * por todos los procesos que lo requieran
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

use Gtd\ClasesTransversal\Config;

/**
 *
 * Util
 */
class Util
{
    private $_config;       // Instancia variables de configuracion

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        // Se instancia la clase de configuración.
        $this->_config = new Config();
    }

    /**
     * compararFechas
     *
     * @param  mixed $primera
     * @param  mixed $segunda
     * @return void
     */
    public function compareDate($primera, $segunda)
    {
        $valoresPrimera = explode("/", $primera);
        $valoresSegunda = explode("/", $segunda);

        $diaPrimera = $valoresPrimera[0];
        $mesPrimera = $valoresPrimera[1];
        $anyoPrimera = $valoresPrimera[2];

        $diaSegunda = $valoresSegunda[0];
        $mesSegunda = $valoresSegunda[1];
        $anyoSegunda = $valoresSegunda[2];

        $diasPrimeraJuliano = gregoriantojd(
            $mesPrimera,
            $diaPrimera,
            $anyoPrimera
        );
        $diasSegundaJuliano = gregoriantojd(
            $mesSegunda,
            $diaSegunda,
            $anyoSegunda
        );

        if (!checkdate($mesPrimera, $diaPrimera, $anyoPrimera)) {
            // "La fecha ".$primera." no es v&aacute;lida";
            return 0;
        } elseif (!checkdate($mesSegunda, $diaSegunda, $anyoSegunda)) {
            // "La fecha ".$segunda." no es v&aacute;lida";
            return 0;
        } else {
            return $diasPrimeraJuliano - $diasSegundaJuliano;
        }
    }

    /**
     * getResponseWs
     *
     * Esta función construye la respuesta final para retornar en el WS
     *
     * @param  mixed $return
     * @return void
     */
    public function getResponseWs($return)
    {
        $resultado = [];
        // Solo se genera transaccion de respuesta si no se presento
        // errores de informacion en el proceso
        if ($return["default"]["status"] !== FALSE) {
            foreach ($return as $key => $value) {
                if ($key != "default") {
                    // Construye la respuesta del WS
                    $desc = $value["data"]["estado_desc"];
                    $msj = $value["data"]["estado"];
                    $arrEst = array("estado_" . strtolower($key) => $desc);
                    $arrMsj = array("mensaje_" . strtolower($key) => $msj);
                    $resultado = array_merge($resultado, $arrEst);
                    $resultado = array_merge($resultado, $arrMsj);
                }
            }
        }
        // Se retornan los estados finales para cada plataforma
        return $resultado;
    }


    /**
     * splitAccount
     *
     * Esta función separa la InstanciaSubcuenta
     *
     * @return void
     */
    public function splitAccount($account)
    {
        $datos = [];
        $datos["subcuentas"] = array();
        // Separa el campo de $instanciaSubCuenta, para obtener los
        // campos individualmente
        if (strstr($account, "|")) {
            $irc = $this->parseData($account);
            $datos["instancia"] = substr($irc[0], 0, strpos($irc[0], "R"));
            $datos["rut"] = substr($irc[0], strpos($irc[0], "R") + 1);
            $subcuentas = count($irc);
            for ($i = 1; $i < $subcuentas; $i++) {
                $datos["subcuentas"][] = $irc[$i];
            }
        }
        return $datos;
    }

    /**
     * splitSerie
     *
     * @param  mixed $series
     * @return void
     */
    public function splitSerie($series)
    {
        // Prepara prefijos de serie
        $prefijos = [];
        foreach ($series as $key => $value) {
            if (substr($value, 0, 6) == "XV1512") {
                $prefijos[$value] = substr($value, 0, 6);
            }
            if (substr($value, 0, 6) == "XV2512") {
                $prefijos[$value] = substr($value, 0, 6);
            }
            if (substr($value, 0, 2) == "G7") {
                $prefijos[$value] = substr($value, 0, 2);
            }
            if (substr($value, 0, 2) == "LA") {
                $prefijos[$value] = substr($value, 0, 2);
            }
            if (substr($value, 0, 2) == "49") {
                $prefijos[$value] = substr($value, 0, 2);
            }
            // Simulacion de dos series de prueba
            // se debe eliminar estas dos series
            if (substr($value, 0, 4) == "0011") {
                $prefijos[$value] = substr($value, 0, 4);
            }
            if (substr($value, 0, 6) == "XV1525") {
                $prefijos[$value] = substr($value, 0, 6);
            }
        }
        return $prefijos;
    }

    /**
     * searchByCode
     *
     * @param  mixed $field
     * @param  mixed $arr
     * @return void
     */
    public function searchByCode($field, $arr)
    {
        foreach ($arr as $val => $data) {
            if ($data['codigo'] === $field) {
                return $val;
            }
        }
    }

    /**
     * setLog
     *
     * @param  mixed $url
     * @param  mixed $message
     * @param  mixed $platform
     * @param  mixed $detail
     * @return void
     */
    public function setLog($url, $message, $platform, $detail = "", $opc="")
    {
        // Genera log en el archivo de logs
        $log = fopen("$url", "a");
        $current = date('d/m/Y H:i:s');
        if ($log) {
            $inicial="";
            if($opc === "INICIO"){
                $inicial= "$current [INICIA PROCESO] - $message\n";
            }

            if($opc === "FIN"){
                $inicial= "$current [FINALIZA PROCESO] - $message\n";
            }
            // Si no es log inicial o final, genera el mensaje
            $msj = "";
            if($inicial===""){

                $msj = "$current [$platform] $message\n";
                // Si es full imprime todo el detalle
                if($this->_config->logIsFull === TRUE) {
                    $det = "";
                    if($detail !== ""){
                        $det = "- ".print_r($detail,TRUE);
                    }
                    $msj = "$current [$platform] $message $det\n";
                }
            }
            fputs($log, $inicial.$msj);
        }
        fclose($log);
    }

    /**
     * parseData
     *
     * @param  mixed $datos
     * @param  mixed $separador
     * @return void
     */
    public function parseData($data, $separator = "|")
    {
        $value = array();
        if (strstr($data, $separator)) {
            $value = explode($separator, $data);
        } else {
            $value[] = $data;
        }

        return $value;
    }

    /**
     * parseHeaders
     *
     * @param  mixed $headers
     * @return void
     */
    function parseHeaders($headers)
    {
        $head = array();
        foreach ($headers as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1])) {
                $head[trim($t[0])] = trim($t[1]);
            }else {
                $head[] = $v;
                if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#", $v, $out)) {
                    $head['response_code'] = intval($out[1]);
                }
            }
        }
        return $head;
    }

    /**
     * startTransaction
     *
     * Esta función registra la transaccion
     *
     * @param  mixed $type
     * @param  mixed $sql
     * @param  mixed $info
     * @return void
     */
    public function startTransaction($type, $sql, $info)
    {
        $id = 0;
        //Prepara los parametros
        $parametros  = "";
        foreach ($info as $key => $value) {
            $parametros .= $value . "_";
        }
        $parametros = substr($parametros, 0, (strlen($parametros) - 1));
        $id = $sql->setTransaction($type, $parametros, $info);
        // Almacena Log de seguimiento
        $this->setLog(
            $this->_config->urlLog,
            "Transaccion Inicial:$id",
            "MEDIADOR",
        );
        return $id;
    }

    /**
     * endTransaction
     *
     * Esta función registra la transaccion
     *
     * @param  mixed $sql
     * @param  mixed $return
     * @param  mixed $id
     * @return void
     */
    public function endTransaction($type, $sql, $return, $id)
    {
        $output = "";
        $estado = "";
        foreach ($return as $key => $value) {
            if ($key != "default") {
                //Construye datos para almacenar en base
                $output .= "estado_$key=>";
                $output .= $value["data"]["estado_desc"] . ",";
                $estado .= $value["data"]["estado"] . "|";
            }
        }
        $output = substr($output, 0, (strlen($output) - 1));
        $estado = substr($estado, 0, (strlen($estado) - 1));
        $cadena = $output . "|" . $estado . "|" . $id;
        // Se registra el resultado del proceso en la base de datos
        $id =  $sql->setTransaction($type, $cadena,$output);
        // Almacena Log de seguimiento
        $this->setLog(
            $this->_config->urlLog,
            "Transaccion Final:$id",
            "MEDIADOR",
        );
        return $id;
    }

    /**
     * setResponse
     *
     * @param  mixed $obj
     * @param  mixed $data
     * @param  mixed $sql
     * @return struct
     */
    public function setResponse($obj, $data, $sql = "")
    {
        error_log($data["messageLog"]);
        // Se arma la estructrua de retorno para la plataforma
        if (!empty($data["platform"])){
            $return[strtolower($data["platform"])]["status"] = $data["state"];
            $return[strtolower($data["platform"])]["data"] = $data["result"];
        }
        // Se asigna estructura de error vacio para excepcion soap
        $return["default"] = $obj->setError("OK");
        // Se verifica si el tipo de respuesta debe generar excepcion soap
        $validaExcepction =  $this->_config->validateExcepcion($data["type"]);
        if($validaExcepction === TRUE){
            // Esta instruccion construeye el error
            // para excepcion de Soap Fault
            $detail = "";
            if(!empty($data["detail"])) {
                $detail = $data["detail"];
            }
            $return["default"] = $obj->setError($data["type"], $detail);
        }

        if(is_object($sql)){
            $sql->setError(
                $data["platform"],
                $data["input"],
                $data["output"],
                $data["ws"]
            );
        }

        return $return;
    }

    /**
     * validateUrl
     *
     * @param  mixed $url
     * @return void
     */
    public function validateUrl($url)
    {

        try {
            $content = @file_get_contents($url);
            if($content !== FALSE){
                $response = $this->parseHeaders($http_response_header);
                $code = $response["response_code"];
            }else{
                $code = 0;
            }
        }catch (Exception $e){
            $code = 0;
        }
        if ($code != 200 && $code != 302 && $code != 304) {
            return FALSE;
        } else {
            return TRUE;
        }
    }
}
