# MACATV

## Pasos a Seguir para que funcione adecuadamente el proyecto

*  Clone este repositorio con git clone url_proyecto
*  En modo Consola, cambise al directorio del proyecto Ejemlpo: `cd MACATV`
*  Asegurese de tener composer instalado
*  Ejecute `composer install` para bajar las dependencias del proyecto
*  Ejecute `composer dumpautoload` para generar la autocarga de clase
   Ejecute `./vendor/phpdocumentor/phpdocumentor/bin/phpdoc -d ../../../../web`
   para generar la autocarga de clase

## Probar Webservice

*  Puede utilizar cualquier cliente de SOAP, ejemplo SoapUI para probar los WS
