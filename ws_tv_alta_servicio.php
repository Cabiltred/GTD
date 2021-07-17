<?php

/**
 * Web Service de Aprovisionamiento para Minerva y VO
 *
 * Este Webservice esta destinado para dar de alta del servicio de GTD y Telsur
 * haciendo uso del Midleware Minerva (SOAP) y VO (REST)
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
 * @package Webservice
 * @author Macarena Cerda Mora      <mcerda@grupogtd.com>
 * @author Franciso Sandoval Iturra <Francisco.Sandoval@grupogtd.com>
 * @copyright 2020 GTD
 * @license https://opensource.org/licenses/MIT MIT License
 * @link http://localhost/
 *
 */

require_once __DIR__ . '/vendor/autoload.php';
// Usa las constantes definidas para la aplicación
use Gtd\ClasesTransversal\Config;
use Aw\Nusoap\NusoapServer;
use Aw\Nusoap\NusoapFault;
use Gtd\ClasesWs\TvAltaServicio;

/**
 * En el namespace Gtd\ClasesTransversal\Config se encuentra la clase para
 * configurar las variables de la aplicación, se tiene para cada ambiente su
 * correspondiente metodo, en el constructor puede cambiar el valor por
 * defecto del ambiente en el que se encuentra la aplicacion
 *
 * public function __construct($ambiente="DEV")
 *
 * Las posibles opciones son
 *
 * DEV = Desarrollo
 * QA  = Pruebas
 * PRD = Produccion
 */

$config = new Config();

// Se define el servidor y su espacio de nombres
$wsURL = $config->urlWsdl . $config->folderWsdl . "ws_tv_alta_servicio.php";
$server = new NusoapServer();
// $server->debug_flag=false;
$server->configurewsdl('ws_tv_alta_servicio', $wsURL);
$server->wsdl->schematargetnamespace = $wsURL;

/**
 * Metodo ws_tv_alta_servicio
 *
 * @Param string $instanciasubcuenta
 *        Varchar(100)
 *        Instancia Sicret y rut del cliente y subcuenta de cada decodificador
 *        a aprovisionar
 *        EJemplo: TV10000R12345|0001|0002
 * @Param $macs la direcciond el dispositivo
 *        Varchar(200)
 *        Direcciones Mac de decodificadores a aprovisionar separados por "|"
 *        Ejemplo: E091532153B0|E09153211412
 * @Param $series
 *        Varchar(200)
 *        Seriales de decodificadores a aprovisionar separados por "|"
 *        Ejemplo: XV151212343534|XV2512231231
 * @Param $planes
 *        Varchar(200)
 *        Planes a aprovisionar separados por "|" con fecha de alta y
 *        fecha de baja
 *        Ejemplo: '10-07-2014',,79|1|35,'10-07-2014','01-11-2014',35
 * @Param $nombre
 *        Varchar(512)
 *        Nombre del cliente
 *        Ejemplo:MACARENADELCARMEN
 * @Param $apellido
 *        Varchar(512)
 *        Apellido del cliente
 *        Ejemplo:CERDAMORA
 * @Param $dirección
 *        Varchar(80)
 *        Dirección del cliente
 *        Ejemplo:ELLIBANO3286
 * @Param $ciudad
 *        Varchar(40)
 *        Ciudad del cliente
 *        Ejemplo:SANTIAGO
 * @Param $tipo_servicio
 *        Varchar (200)
 *        Tipo de Servicio a activar
 *        Ejemplo:IPTV,RF
 * @Param $serviceId
 *        Varchar (200)
 *        Cadena formada del servicio id
 *        Ejemplo:STGO-0290-LOCA-1-16-6-7
 * @Param $serviceOrder
 *        Varchar (200)
 *        Indica la orden de servicio a instalar
 *        Ejemplo:FTTH339526
 * @Param $totalDecosCliente
 *        Varchar (2)
 *        Indica la cantidad de decos del cliente
 *        Ejemplo:2
 *
 * @return Struct
 */

function ws_tv_alta_servicio(
    $instanciasubcuenta,
    $macs,
    $series,
    $planes,
    $nombre,
    $apellido,
    $direccion,
    $ciudad,
    $tipoServicio,
    $region,
    $login,
    $email,
    $serviceId,
    $serviceOrder,
    $totalDecosCliente
)
{
    error_log($nombre . PHP_EOL . $apellido . PHP_EOL . $direccion);
    // Se arma la estructura que llevara todos los atributos recogidos por el
    // Webserive a la Clase que dara de alta el servicio
    $info = array(
        "instanciaSubCuenta" => $instanciasubcuenta,
        "macs"              => $macs,
        "series"            => $series,
        "planes"            => $planes,
        "nombre"            => $nombre,
        "apellido"          => $apellido,
        "direccion"         => $direccion,
        "ciudad"            => $ciudad,
        "tipoServicio"      => $tipoServicio,
        "region"            => $region,
        "login"             => $login,
        "email"             => $email,
        "serviceId"         => $serviceId,
        "serviceOrder"      => $serviceOrder,
        "totalDecosCliente" => $totalDecosCliente
    );

    // Se instancia la clase de TvAltaServicio
    $obj = new TvAltaServicio($info);
    // Se llama metodo que da de alta el servicio en Minerva
    $resultado = $obj->altaServicio();
    if ($resultado["default"]["status"] === FALSE) {
        return new NusoapFault(
            $resultado["default"]["error"]["faultCode"],
            $resultado["default"]["error"]["faultActor"],
            $resultado["default"]["error"]["faultString"],
            $resultado["default"]["error"]["faultDetail"]
        );
    }else{
        // Aqui se unen los resultados de cada plataforma para retornar
        return $resultado["default"]["respuesta"];
    }

    // Se retorna el resultado en formato SOAP
}
//Para el metodo WS_ALTA_SERVICIO
$parameters = array(
    'instanciaRutSubcuenta' => 'xsd:string',
    'macs'                  => 'xsd:string',
    'series'                => 'xsd:string',
    'planes'                => 'xsd:string',
    'nombre'                => 'xsd:string',
    'apellido'              => 'xsd:string',
    'direccion'             => 'xsd:string',
    'ciudad'                => 'xsd:string',
    'tipo_servicio'         => 'xsd:string',
    'region'                => 'xsd:string',
    'login'                 => 'xsd:string',
    'email'                 => 'xsd:string',
    'serviceId'             => 'xsd:string',
    'serviceOrder'          => 'xsd:string',
    'totalDecosCliente'     => 'xsd:string'
);

$server->wsdl->addComplexType(
    'resultado',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'estado_minerva' => array(
          'name' => 'estado_minerva',
          'type' => 'xsd:string'
        ),
        'estado_vo' => array(
          'name' => 'estado_vo',
          'type' => 'xsd:string'
        ),
        'mensaje_minerva' => array(
          'name' => 'mensaje_minerva',
          'type' => 'xsd:string'
        ),
        'mensaje_vo' => array(
          'name' => 'mensaje_vo',
          'type' => 'xsd:string'
        ),
    )
); //datos de salida

$server->register(
    'ws_tv_alta_servicio', //nombre de la funcion
    $parameters, //parametros de entrada
    array('return' => 'tns:resultado'),
    $wsURL
);

// Se setea $HTTP_RAW_POST_DATA para que PHP no le de formato al mensaje
// y lo envíe tal cual se genera
if (!isset($HTTP_RAW_POST_DATA)) {
    $HTTP_RAW_POST_DATA = file_get_contents('php://input');
}

//Se publica el servicio
$server->service($HTTP_RAW_POST_DATA);
