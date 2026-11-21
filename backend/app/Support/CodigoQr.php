<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Dibuja el QR del carné. Se genera en el servidor, como SVG, para que se vea
 * sin depender de JavaScript y salga nítido al imprimirlo.
 */
class CodigoQr
{
    public static function svg(string $texto, int $lado = 240): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($lado, 2), new SvgImageBackEnd)))->writeString($texto);

        // Sin la cabecera XML, para poder incrustarlo dentro de la página.
        return substr($svg, (int) strpos($svg, '<svg'));
    }
}
